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
 *
 * El credito disponible y la regla del pre-vale YA se validaron y se
 * apartaron cuando la distribuidora mando la solicitud (ver
 * RequestVoucherService) -- aqui no se vuelven a checar ni a descontar. Si
 * se hiciera aqui otra vez, la distribuidora perderia el monto DOS veces (una
 * al pedirlo, otra al aprobarlo) y el chequeo de "credito suficiente"
 * saldria mal: available_credit ya refleja el apartado de ESTA solicitud, no
 * tendria sentido exigir que alcance para cubrirse a si misma de nuevo.
 */
final class ApproveVoucherService
{
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

            $branchSetting = BranchSetting::query()
                ->firstOrCreate(['branch_id' => $distributor->branch_id])
                ->refresh();

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

            // La primera quincena de un vale recien otorgado cae en el corte
            // que esta sucursal tiene ACTUALMENTE ABIERTO (no cerrado), SIN
            // IMPORTAR que tan lejos de la fecha real este ese corte -- aqui
            // las distribuidoras generan y cierran cortes de prueba muy
            // adelantados o atrasados respecto a hoy (ej. llevan la
            // simulacion hasta diciembre aunque hoy sea 26 de agosto), y el
            // vale debe caer en ESE corte abierto (su scheduled_at), no en
            // uno calculado con el reloj real.
            $openCutoff = Cutoff::query()
                ->where('branch_id', $distributor->branch_id)
                ->where('cutoff_type', CutoffType::PAGOS)
                ->where('status', '!=', CutoffStatus::CERRADO)
                ->orderByDesc('period_start')
                ->first();

            $dueDays = (int) ($branchSetting->payment_due_days ?? 15);
            $frequencyDays = (int) ($branchSetting->payment_frequency_days ?? 14);

            if ($openCutoff !== null) {
                $dueDate = $openCutoff->scheduled_at->copy();
            } else {
                // No hay corte abierto EN ESTE MOMENTO (el ultimo se cerro y
                // todavia no se genera el siguiente) -- si la sucursal YA
                // tiene historial de cortes (aunque esten cerrados), el vale
                // debe seguir esa MISMA linea de tiempo (el periodo justo
                // despues del ultimo que tuvo la sucursal), no la fecha real
                // del reloj: el reporte que motivo esto fue exactamente este
                // caso -- una sucursal ya simulada hasta diciembre, sin
                // ningun corte abierto en el momento de otorgar un vale
                // nuevo, terminaba con el vale fechado en septiembre (segun
                // el reloj real) en vez de diciembre (donde en realidad va la
                // sucursal); cuando el gerente generaba el siguiente corte
                // consecutivo de diciembre (el unico que GenerateCutoffService
                // le permite generar), el vale nunca aparecia ahi. Solo si la
                // sucursal JAMAS ha tenido NINGUN corte (ni abierto ni
                // cerrado) se usa el reloj real como ultimo recurso.
                $lastCutoff = Cutoff::query()
                    ->where('branch_id', $distributor->branch_id)
                    ->where('cutoff_type', CutoffType::PAGOS)
                    ->orderByDesc('scheduled_at')
                    ->first();

                $dueDate = $lastCutoff !== null
                    ? $periods->nextPeriodEnd($lastCutoff->scheduled_at->copy(), $branchSetting->cutoff_day)
                    : $periods->nextPeriodEnd(now(), $branchSetting->cutoff_day);
            }

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
