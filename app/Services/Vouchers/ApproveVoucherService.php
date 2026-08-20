<?php

declare(strict_types=1);

namespace App\Services\Vouchers;

use App\Enums\CustomerDistributorRelationshipStatus;
use App\Enums\CustomerStatus;
use App\Enums\VoucherRequestStatus;
use App\Enums\VoucherStatus;
use App\Models\BranchSetting;
use App\Models\CustomerDistributor;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRequest;
use App\Services\Cutoffs\CutoffPeriodCalculator;
use App\Services\Financial\FinancialCalculationService;
use Illuminate\Support\Facades\DB;

/**
 * Aprobar una solicitud de vale y otorgarselo al cliente son el MISMO paso
 * para la cajera (ver aclaracion del negocio): revisa/verifica al cliente en
 * el modal de "Decidir" y, si todo esta bien, aprueba — eso YA es la entrega
 * del dinero. Por eso este servicio crea el Voucher directamente en estado
 * ACTIVO (antes se creaba en APROBADO y hacia falta un segundo paso de
 * "Entregar vale" pidiendo referencia de transferencia y numero de
 * autorizacion, que en realidad son datos de la conciliacion con la
 * distribuidora — no algo que la cajera captura al dar el vale). El primer
 * estado de cuenta (CutoffRelation) que le paga a la distribuidora se genera
 * despues, por separado, en el siguiente corte (GenerateCutoffService), que
 * ya solo toma vales ACTIVO/PAGO_PARCIAL/MOROSO.
 */
final class ApproveVoucherService
{
    public function __construct(
        private readonly FinancialCalculationService $financial,
    ) {}

    public function execute(User $user, VoucherRequest $voucherRequest, CutoffPeriodCalculator $periods = new CutoffPeriodCalculator()): Voucher
    {
        return DB::transaction(function () use ($user, $voucherRequest, $periods): Voucher {
            if ($voucherRequest->status !== VoucherRequestStatus::PENDIENTE) {
                abort(422, 'La solicitud ya fue resuelta.');
            }

            /** @var VoucherRequest $voucherRequest */
            $voucherRequest->load(['distributor', 'customer']);

            $distributor = $voucherRequest->distributor;
            $snapshot = $voucherRequest->snapshot_json ?? [];
            // total_debt_amount es el total COMPLETO que le cobra al cliente (con
            // la comisión de la distribuidora incluida — ver
            // FinancialCalculationService), así que se guarda tal cual en el vale
            // (current_balance también arranca ahí, para que las quincenas
            // completas lo vayan bajando a cero).
            $totalDebt = (float) ($snapshot['total_debt_amount'] ?? $voucherRequest->requested_amount);
            // El crédito disponible mide cuánto CAPITAL (principal) puede tener
            // prestado la distribuidora a la vez, no el total a cobrar (que ya
            // trae intereses, seguro y comisiones encima) -- por eso se compara y
            // se descuenta solo el principal. Se libera de vuelta cuando el vale
            // se termina de pagar por completo (ver SettleCutoffRelationService).
            $principal = (float) ($snapshot['principal'] ?? $voucherRequest->requested_amount);
            $availableCredit = (float) $distributor->available_credit;

            if ($availableCredit < $principal) {
                abort(422, 'El crédito disponible de la distribuidora es insuficiente para aprobar el vale.');
            }

            $branchSetting = BranchSetting::query()
                ->firstOrCreate(['branch_id' => $distributor->branch_id])
                ->refresh();

            $reactivationPending = $distributor->prevale_required_after_credit_increase_at !== null;

            $preValeResult = $this->financial->validatePreVale(
                requestedAmount: (float) $voucherRequest->requested_amount,
                availableCredit: $availableCredit,
                totalCreditLimit: (float) $distributor->credit_limit,
                maxPercentage: (float) $branchSetting->pre_vale_max_percentage,
                toleranceAmount: (float) $branchSetting->pre_vale_tolerance_amount,
                reactivationPending: $reactivationPending,
            );

            if (! $preValeResult->allowed) {
                abort(422, $preValeResult->reason ?? 'El monto excede el máximo permitido para el primer vale.');
            }

            $customer = $voucherRequest->customer;

            if ($voucherRequest->is_pre_vale) {
                CustomerDistributor::query()
                    ->where('distributor_id', $distributor->id)
                    ->where('customer_id', $voucherRequest->customer_id)
                    ->where('relationship_status', CustomerDistributorRelationshipStatus::ACTIVA->value)
                    ->update(['prevale_approved' => true]);

                // Un cliente sin verificar por la cajera pudo solicitar este vale
                // precisamente por ser un prevale (ver RequestVoucherService); al
                // aprobarse, el cliente queda activo sin esperar la verificacion manual.
                if ($customer->status !== CustomerStatus::ACTIVO || $customer->verified_at === null) {
                    $customer->update([
                        'status' => CustomerStatus::ACTIVO,
                        'verified_at' => $customer->verified_at ?? now(),
                        'verified_by_user_id' => $customer->verified_by_user_id ?? $user->id,
                    ]);
                    $customer->refresh();
                }
            }

            // Red de seguridad: un vale normal (no pre-vale) siempre debe llegar
            // aqui con el cliente ya verificado por la cajera en el propio paso de
            // "Decidir" (antes de habilitar el boton de aprobar).
            if ($customer->status !== CustomerStatus::ACTIVO || $customer->verified_at === null) {
                abort(422, 'El cliente debe ser verificado antes de poder otorgarle el vale.');
            }

            $distributor->decrement('available_credit', $principal);

            if ($reactivationPending) {
                // La regla del 50% ya se aplicó a este vale (el primero desde el
                // aumento de línea); se libera para que los siguientes vuelvan a
                // comportarse como vale digital normal.
                $distributor->update(['prevale_required_after_credit_increase_at' => null]);
            }

            // La primera quincena de un vale recien otorgado SIEMPRE cae en el
            // periodo de corte que sigue al periodo actual (nunca en el periodo
            // donde se otorga, sin importar que tan temprano caiga): el cliente
            // apenas recibio el vale, no le puede tocar pagar en unos dias solo
            // porque el periodo actual ya casi cierra. Ver CutoffPeriodCalculator
            // y branch_settings.cutoff_day (1-15/16-31 por default).
            $dueDays = (int) ($branchSetting->payment_due_days ?? 15);
            $frequencyDays = (int) ($branchSetting->payment_frequency_days ?? 14);
            $dueDate = $periods->nextPeriodEnd(now(), $branchSetting->cutoff_day);

            $voucherNumber = 'V-'.((int) Voucher::query()->max('id') + 1);

            $voucher = Voucher::query()->create([
                'voucher_number' => $voucherNumber,
                'distributor_id' => $distributor->id,
                'customer_id' => $voucherRequest->customer_id,
                'financial_product_id' => $voucherRequest->financial_product_id,
                'voucher_request_id' => $voucherRequest->id,
                'branch_id' => $distributor->branch_id,
                'created_by_user_id' => $voucherRequest->created_by_user_id,
                'approved_by_user_id' => $user->id,
                'disbursed_by_user_id' => $user->id,
                'status' => VoucherStatus::ACTIVO,
                'is_pre_vale' => $voucherRequest->is_pre_vale,
                'amount' => $snapshot['principal'] ?? $voucherRequest->requested_amount,
                'company_commission_percentage_snapshot' => $snapshot['company_commission_percentage_snapshot'] ?? 0,
                'company_commission_amount' => $snapshot['company_commission_amount'] ?? 0,
                'insurance_amount_snapshot' => $snapshot['insurance_amount_snapshot'] ?? 0,
                'interest_percentage_snapshot' => $snapshot['interest_percentage_snapshot'] ?? 0,
                'interest_amount' => $snapshot['interest_amount'] ?? 0,
                'distributor_profit_percentage_snapshot' => $snapshot['distributor_profit_percentage_snapshot'] ?? 0,
                'distributor_profit_amount' => $snapshot['distributor_profit_amount'] ?? 0,
                'late_fee_amount_snapshot' => $snapshot['late_fee_amount_snapshot'] ?? 0,
                'total_debt_amount' => $totalDebt,
                'fortnightly_payment_amount' => $snapshot['fortnightly_payment_amount'] ?? 0,
                'total_fortnights' => $snapshot['total_fortnights'] ?? 0,
                'payments_made' => 0,
                'current_balance' => $totalDebt,
                'issued_at' => now(),
                'transferred_at' => now(),
                'payment_due_date' => $dueDate->toDateString(),
                'early_payment_start_date' => $dueDate->copy()->subDays($dueDays - 1)->toDateString(),
                'early_payment_end_date' => $dueDate->copy()->subDays($dueDays - $frequencyDays)->toDateString(),
                'is_canceled' => false,
            ]);

            $voucherRequest->update([
                'status' => VoucherRequestStatus::APROBADO,
                'decided_by_user_id' => $user->id,
                'decided_at' => now(),
            ]);

            return $voucher;
        });
    }
}
