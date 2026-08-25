<?php

declare(strict_types=1);

namespace App\Services\Vouchers;

use App\Enums\CustomerDistributorRelationshipStatus;
use App\Enums\CustomerStatus;
use App\Enums\VoucherRequestStatus;
use App\Enums\VoucherStatus;
use App\Models\CustomerDistributor;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRequest;
use Illuminate\Support\Facades\DB;

/**
 * Aprobar una solicitud y entregarle el dinero al cliente son pasos
 * separados (ver aclaracion del negocio): el correo con los datos del vale
 * ya se le mando al cliente desde que la distribuidora lo pidio (ver
 * RequestVoucherService) -- no espera a que la cajera lo apruebe. Aqui solo
 * se materializa el Voucher en estado APROBADO, con el MISMO numero que ya
 * se le mando al cliente ('V-' + id de la solicitud), para que coincida con
 * lo que trae en su correo cuando se presente. El cliente lleva ese correo
 * en persona con la cajera, quien lo "ferea" (valida) y ahi si dispersa el
 * dinero — ver DisburseVoucherService, que es el que pone el vale en ACTIVO
 * y captura la referencia de transferencia y el numero de autorizacion.
 *
 * El credito disponible y la regla del pre-vale YA se validaron y se
 * apartaron cuando la distribuidora mando la solicitud (ver
 * RequestVoucherService) -- aqui no se vuelven a checar ni a descontar. Si
 * se hiciera aqui otra vez, el chequeo saldria mal: available_credit ya
 * refleja el apartado de ESTA solicitud, no tendria sentido exigir que
 * alcance para cubrirse a si misma de nuevo.
 */
final class ApproveVoucherService
{
    public function execute(User $user, VoucherRequest $voucherRequest): Voucher
    {
        return DB::transaction(function () use ($user, $voucherRequest): Voucher {
            if ($voucherRequest->status !== VoucherRequestStatus::PENDIENTE) {
                abort(422, 'La solicitud ya fue resuelta.');
            }

            /** @var VoucherRequest $voucherRequest */
            $voucherRequest->load(['distributor.person', 'customer.person']);

            $distributor = $voucherRequest->distributor;
            $snapshot = $voucherRequest->snapshot_json ?? [];
            // total_debt_amount es el total COMPLETO que le cobra al cliente (con
            // la comisión de la distribuidora incluida — ver
            // FinancialCalculationService), así que se guarda tal cual en el vale
            // (current_balance también arranca ahí, para que las quincenas
            // completas lo vayan bajando a cero).
            $totalDebt = (float) ($snapshot['total_debt_amount'] ?? $voucherRequest->requested_amount);

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

            // Mismo numero que ya se le mando por correo al cliente al pedir el
            // vale (ver RequestVoucherService::sendIssuedMail), para que
            // coincida con lo que trae impreso/en el correo cuando se presente.
            $voucherNumber = 'V-'.$voucherRequest->id;

            // El estado ACTIVO, la referencia de transferencia, el numero de
            // autorizacion y las fechas de pago (que dependen de CUANDO se
            // dispersa, no de cuando se aprueba) se calculan y capturan despues,
            // en DisburseVoucherService, cuando el cliente se presenta con la
            // cajera a "ferear" el vale y recibir el dinero.
            $voucher = Voucher::query()->create([
                'voucher_number' => $voucherNumber,
                'distributor_id' => $distributor->id,
                'customer_id' => $voucherRequest->customer_id,
                'financial_product_id' => $voucherRequest->financial_product_id,
                'voucher_request_id' => $voucherRequest->id,
                'branch_id' => $distributor->branch_id,
                'created_by_user_id' => $voucherRequest->created_by_user_id,
                'approved_by_user_id' => $user->id,
                'status' => VoucherStatus::APROBADO,
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
                // El vale se considera "emitido" desde que la distribuidora lo
                // pidio (ese es el correo que recibio el cliente), no desde que
                // la cajera lo aprueba -- para que la fecha de caducidad
                // (issued_at + voucher_expiration_days) coincida con la que ya
                // se le informo por correo.
                'issued_at' => $voucherRequest->created_at,
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
