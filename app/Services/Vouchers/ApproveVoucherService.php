<?php

declare(strict_types=1);

namespace App\Services\Vouchers;

use App\Enums\CustomerDistributorRelationshipStatus;
use App\Enums\CustomerStatus;
use App\Enums\CutoffStatus;
use App\Enums\CutoffType;
use App\Enums\VoucherRequestStatus;
use App\Enums\VoucherStatus;
use App\Models\BranchSetting;
use App\Models\CustomerDistributor;
use App\Models\Cutoff;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRequest;
use App\Services\Cutoffs\CutoffPeriodCalculator;
use App\Services\Financial\FinancialCalculationService;
use Illuminate\Support\Facades\DB;

/**
 * Aprobar una solicitud de vale y entregarle el dinero al cliente son el
 * MISMO paso para la cajera (aclaracion del negocio): revisa/verifica al
 * cliente en el modal de "Decidir" y, si todo esta bien, aprueba -- eso YA es
 * la entrega. Por eso este servicio deja el Voucher directamente en estado
 * ACTIVO, con sus fechas de pago calculadas (antes esto se dividia en dos
 * pasos -- aprobar dejaba el vale en APROBADO, y hacia falta un segundo paso
 * de "Entregar vale" pidiendole a la cajera capturar a mano una referencia de
 * transferencia y un numero de autorizacion -- pero el dinero que se entrega
 * sale de la linea de credito de la distribuidora, no de una transferencia
 * bancaria real que alguien deba anotar, asi que no hay nada que la cajera
 * tenga que capturar ahi: transfer_reference/authorized_number se generan
 * solos, nada mas como folio interno de auditoria/conciliacion).
 */
final class ApproveVoucherService
{
    public function __construct(
        private readonly FinancialCalculationService $financial,
    ) {}

    public function execute(User $user, VoucherRequest $voucherRequest, CutoffPeriodCalculator $periods = new CutoffPeriodCalculator()): Voucher
    {
        $voucher = DB::transaction(function () use ($user, $voucherRequest, $periods): Voucher {
            if ($voucherRequest->status !== VoucherRequestStatus::PENDIENTE) {
                abort(422, 'La solicitud ya fue resuelta.');
            }

            /** @var VoucherRequest $voucherRequest */
            $voucherRequest->load(['distributor.person', 'customer.person']);

            $distributor = $voucherRequest->distributor;

            // Red de seguridad: si la solicitud se creó antes de que la
            // distribuidora quedara MOROSA (ver MarkOverdueRelationsCommand),
            // RequestVoucherService ya no la habría dejado crearla hoy, pero
            // pudo quedar pendiente de aprobación de antes -- no se debe
            // aprobar/entregar un vale a una distribuidora bloqueada aunque
            // la solicitud ya existiera.
            if (! $distributor->can_issue_vouchers) {
                abort(422, 'La distribuidora está bloqueada por adeudo vencido y no puede recibir vales nuevos hasta regularizar el pago.');
            }

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

            // Mismo numero que ya se le mando por correo al cliente al pedir el
            // vale (ver RequestVoucherService::sendIssuedMail), para que
            // coincida con lo que trae impreso/en el correo cuando se presente.
            $voucherNumber = 'V-'.$voucherRequest->id;

            // La primera quincena de un vale recien otorgado cae en el periodo
            // del corte que esta sucursal tiene ACTUALMENTE ABIERTO (no
            // cerrado) -- revision del profesor. No se usa el reloj real
            // (now()) como referencia principal porque aqui las
            // distribuidoras generan y cierran cortes de prueba muy
            // adelantados o atrasados respecto a la fecha real (ver el corte
            // mas reciente de la sucursal, que puede estar meses adelante o
            // atras del dia de hoy) -- "el periodo actual" para un vale
            // nuevo es el periodo del corte que ya esta en curso, no el que
            // tocaria segun la fecha real. Si la sucursal ya tiene un corte
            // sin cerrar, el vale cae exactamente en su fecha limite
            // (scheduled_at), para que aparezca ahi al reprocesarlo (ver
            // ReprocessCutoffService). Si todavia no tiene ningun corte
            // abierto (sucursal nueva, o se cerraron todos y no se ha
            // generado el siguiente), se usa el reloj real como respaldo
            // (CutoffPeriodCalculator + branch_settings.cutoff_day, 1-15/16-31
            // por default) -- el primer corte que se genere despues cae en
            // ese mismo periodo.
            $openCutoff = Cutoff::query()
                ->where('branch_id', $distributor->branch_id)
                ->where('cutoff_type', CutoffType::PAGOS)
                ->where('status', '!=', CutoffStatus::CERRADO)
                ->orderByDesc('period_start')
                ->first();

            $dueDays = (int) ($branchSetting->payment_due_days ?? 15);
            $frequencyDays = (int) ($branchSetting->payment_frequency_days ?? 14);
            $dueDate = $openCutoff !== null
                ? $openCutoff->scheduled_at->copy()
                : $periods->currentPeriodEnd(now(), $branchSetting->cutoff_day);

            // No hay una transferencia bancaria real que registrar (el dinero
            // sale de la linea de credito de la distribuidora, se entrega en el
            // momento) -- estos dos folios son solo para conciliacion/auditoria
            // interna, con el mismo formato que ya usa CutoffRelation.payment_reference
            // para su propio folio auto-generado (ver GenerateCutoffService).
            $transferReference = 'TRANS-'.mb_strtoupper(mb_substr(md5(uniqid((string) $distributor->id, true)), 0, 10));
            $authorizedNumber = 'AUT-'.$voucherRequest->id;

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
                'transfer_reference' => $transferReference,
                'authorized_number' => $authorizedNumber,
                // El vale se considera "emitido" desde que la distribuidora lo
                // pidio (ese es el correo que recibio el cliente), no desde que
                // la cajera lo aprueba -- para que la fecha de caducidad
                // (issued_at + voucher_expiration_days) coincida con la que ya
                // se le informo por correo. transferred_at, en cambio, SI es
                // ahora: es el momento real en que la cajera le entrego el
                // dinero.
                'issued_at' => $voucherRequest->created_at,
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

        return $voucher;
    }
}
