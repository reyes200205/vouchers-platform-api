<?php

declare(strict_types=1);

namespace App\Services\Cutoffs;

use App\Enums\CutoffRelationStatus;
use App\Enums\PointMovementType;
use App\Enums\VoucherStatus;
use App\Models\CutoffRelation;
use App\Models\PointMovement;
use App\Models\PointSetting;
use App\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Marca como vencidas las relaciones de corte que nadie pagó a tiempo, y le
 * aplica a la distribuidora las consecuencias del atraso.
 *
 * Cuando el pago es a tiempo, la distribuidora cobra la quincena completa del
 * cliente (payment_amount, ya incluye su comisión de categoría — ver
 * FinancialCalculationService) pero solo remite a la sucursal
 * payment_amount - comisión (ver GenerateCutoffService::calculateDistributorCommission)
 * — se queda con esa comisión como ganancia. Si NO paga a tiempo, la
 * distribuidora ya no gana nada: debe remitir la quincena COMPLETA
 * (payment_amount, que ya trae la comisión incluida — no hay que volver a
 * sumarla) más la multa fija del producto.
 *
 * Esto es lo que después arrastra el siguiente corte como "quincena atrasada
 * + multa" junto (pero aparte, como item propio) de la quincena normal del
 * periodo — ver GenerateCutoffService. Esa ULTIMA quincena facturada del vale
 * (ej. 8/8) es la unica que se sigue arrastrando de corte en corte mientras
 * siga sin pagarse -- GenerateCutoffService ya no genera quincenas "nuevas"
 * (9/8, 10/8...) para un vale que ya se facturo por completo. Revision del
 * profesor: cada corte que pasa sin que se pague, se le vuelve a sumar OTRA
 * multa encima de la que ya traia (no una multa fija y unica por vale) --
 * ver closeRelation() abajo.
 */
final class MarkOverdueRelationsService
{
    /**
     * @return int number of relations marked
     */
    public function execute(?Carbon $asOf = null): int
    {
        $asOf ??= now();

        return DB::transaction(function () use ($asOf): int {
            $relations = CutoffRelation::query()
                ->where('status', CutoffRelationStatus::GENERADA)
                ->whereDate('payment_due_date', '<', $asOf->toDateString())
                ->get();

            foreach ($relations as $relation) {
                $this->closeRelation($relation);
            }

            return $relations->count();
        });
    }

    /**
     * Publico porque CloseCutoffService reutiliza esta misma logica para
     * cerrar manualmente una relacion antes de su payment_due_date (cuando un
     * gerente cierra el corte a la fuerza sin esperar a que se venza sola).
     */
    public function closeRelation(CutoffRelation $relation): void
    {
        $items = $relation->items()->get();
        $totalLateFees = 0.0;
        $totalAmountDue = 0.0;

        foreach ($items as $item) {
            $voucher = $item->voucher_id !== null ? Voucher::query()->find($item->voucher_id) : null;
            $isCarryover = $item->origin_relation_id !== null;

            // La multa se lee del snapshot inmutable que quedó grabado en el vale al
            // emitirlo (vouchers.late_fee_amount_snapshot), tomado en su momento del
            // producto financiero (financial_products.late_fee_amount), no de la
            // configuración actual de la sucursal ni del producto en vivo.
            $lateFeeIncrement = round((float) ($voucher?->late_fee_amount_snapshot ?? 0.0), 2);
            // Una quincena de la comision que la distribuidora se hubiera
            // quedado si hubiera pagado a tiempo (distributor_profit_amount
            // se cotiza sobre el vale completo, repartido en partes iguales
            // entre sus quincenas -- ver GenerateCutoffService::calculateDistributorCommission).
            // Ya NO es solo informativo (ver commission_forfeited_amount en
            // CutoffRelationItem): se suma de verdad al total que debe la
            // distribuidora, igual que la multa, cada vez que la quincena
            // FINAL del vale se vuelve a vencer sin pagarse.
            $commissionLossIncrement = $voucher !== null && $voucher->total_fortnights > 0
                ? round((float) $voucher->distributor_profit_amount / $voucher->total_fortnights, 2)
                : 0.0;
            $alreadyHasFee = (float) $item->late_fee_amount > 0;
            // Solo la quincena FINAL del vale (installment_number ==
            // total_payments, ej. 8/8) es la que se sigue arrastrando
            // congelada corte tras corte sin generarse quincenas nuevas
            // (ver GenerateCutoffService) -- es la unica a la que tiene
            // sentido subirle la multa cada vez que vuelve a vencerse, ya
            // que sigue siendo la MISMA deuda. Una quincena que NO es la
            // final (ej. la 3/8 se atraso pero el vale siguio facturando la
            // 4/8, 5/8...) es una deuda DISTINTA y aparte -- si tambien se
            // le fuera acumulando multa cada corte que sigue sin pagarse,
            // cada quincena atrasada del vale terminaria multiplicando su
            // propia multa por separado, no solo la ultima. Para esas, se
            // mantiene la regla original: una sola multa, nunca se duplica.
            $isFinalInstallment = $item->installment_number === $item->total_payments;

            if (! $alreadyHasFee) {
                // Primera vez que esta quincena se vence (nunca antes se le
                // habia cobrado multa).
                //
                // Un item de arrastre (origin_relation_id no nulo) que TODAVÍA no
                // traía multa (se arrastró mientras su relación seguía GENERADA,
                // sin haberse vencido todavía) ya trae en payment_amount el monto
                // exacto que se le debe a la sucursal de un periodo anterior -- no
                // viene de redondear una quincena, así que aquí sí se suma la
                // multa directo sobre eso.
                //
                // Un item de la quincena normal (sin origin) SÍ viene de
                // payment_amount ya redondeado al piso para el cobro del cliente
                // (FinancialCalculationService) -- sumarle la multa ahí perdería
                // los mismos centavos otra vez. Por eso la multa se suma sobre el
                // total exacto del vale sin redondear (total_debt_amount /
                // total_fortnights), igual que GenerateCutoffService::calculateNetRemit
                // hace para el pago a tiempo, y el resultado se redondea al piso
                // al peso entero al final (regla de negocio: siempre floor, nunca
                // deja centavos) -- no round().
                if (! $isCarryover && $voucher !== null && $voucher->total_fortnights > 0) {
                    $grossPerFortnight = ((float) $voucher->total_debt_amount) / $voucher->total_fortnights;
                    $lineTotal = floor($grossPerFortnight + $lateFeeIncrement);
                } else {
                    $lineTotal = floor((float) $item->payment_amount + $lateFeeIncrement);
                }

                $newLateFee = $lateFeeIncrement;
                // Se registra la comision de una quincena como perdida (para
                // mostrarla en el detalle), pero NO se suma aparte a
                // $lineTotal aqui -- ya viene incluida en $grossPerFortnight
                // (el total exacto del vale entre sus quincenas, sin
                // descontar comision) usado arriba.
                $newCommissionForfeited = $commissionLossIncrement;
            } elseif ($isFinalInstallment) {
                // Esta quincena YA traia multa de una vez anterior (viene de
                // un arrastre cuya relacion de origen ya se habia vencido), ES
                // la ultima quincena del vale, y la relacion que la recibio
                // TAMBIEN se vencio sin pagarse: revision del profesor -- cada
                // corte que pasa sin pagarse le suma OTRA multa Y OTRA
                // comision perdida completas encima de lo que ya traia ("los
                // intereses -- la comision perdida mas la multa" -- no son
                // cargos fijos unicos, se van acumulando mientras el atraso
                // siga). Sigue siendo la MISMA ultima quincena la que se
                // arrastra (GenerateCutoffService ya no genera quincenas
                // nuevas para un vale completamente facturado), asi que esto
                // se lee como "la deuda de esa quincena crece cada periodo".
                $newLateFee = round((float) $item->late_fee_amount + $lateFeeIncrement, 2);
                $newCommissionForfeited = round((float) $item->commission_forfeited_amount + $commissionLossIncrement, 2);
                $lineTotal = floor((float) $item->line_total_amount + $lateFeeIncrement + $commissionLossIncrement);
            } else {
                // Ya traia multa (y comision perdida) de una vez anterior
                // pero NO es la quincena final del vale (es una quincena
                // intermedia que se atraso mientras el vale seguia
                // facturando otras despues) -- se mantiene la regla
                // original: ni la multa ni la comision perdida se duplican,
                // son un cargo unico por quincena. Si tambien se fueran
                // acumulando aqui, cada quincena atrasada del vale
                // terminaria multiplicando sus propios cargos por separado
                // en vez de solo la ultima.
                $newLateFee = (float) $item->late_fee_amount;
                $newCommissionForfeited = (float) $item->commission_forfeited_amount;
                $lineTotal = (float) $item->line_total_amount;
            }

            // payment_amount ya es la quincena COMPLETA (con la comisión de la
            // distribuidora incluida — ver FinancialCalculationService), así que no
            // hay que volver a sumarle la comisión aquí: nada más se pone la
            // comisión en 0 (ya no se la queda) y se le agrega/aumenta la multa.
            $item->update([
                'is_late_payment' => true,
                'commission_amount' => 0.00,
                'late_fee_amount' => $newLateFee,
                'commission_forfeited_amount' => $newCommissionForfeited,
                'line_total_amount' => $lineTotal,
            ]);

            $totalLateFees += (float) $item->late_fee_amount;
            $totalAmountDue += (float) $item->line_total_amount;

            if ($voucher !== null && ! in_array($voucher->status, [
                VoucherStatus::PAGADO,
                VoucherStatus::LIQUIDADO,
                VoucherStatus::CANCELADO,
                VoucherStatus::REVERSADO,
            ], true)) {
                $voucher->update(['status' => VoucherStatus::MOROSO]);
            }
        }

        // total_amount_due es la suma de line_total_amount de cada item (ya
        // recalculados arriba), no total_payment + multa + arrastre por
        // separado -- esos agregados se quedarían con el mismo desfase de
        // centavos que el item por item ya corrige.
        $relation->update([
            'status' => CutoffRelationStatus::VENCIDA,
            'total_late_fees' => round($totalLateFees, 2),
            'total_commission' => 0.00,
            'total_amount_due' => round($totalAmountDue, 2),
        ]);

        $this->applyLatePenaltyToPoints($relation);
    }

    /**
     * "Pagos fuera de tiempo, se elimina el 20% del total de puntos" -- a
     * diferencia del bono de puntos (que solo se otorga cuando una relación
     * SÍ se termina pagando, ver SettleCutoffRelationService::awardPoints,
     * que ya reduce en 20% lo que se GANA en esa liquidación tardía), esta
     * penalización es independiente de si la distribuidora algún día paga:
     * se aplica en el momento mismo en que el corte se vence sin pagar,
     * quitándole el 20% (configurable) de TODO su saldo de puntos actual --
     * no solo de lo que hubiera ganado en este corte. Sin esto, una
     * distribuidora que encadena varios cortes vencidos sin pagar nunca
     * pierde puntos (nunca llega a PAGADA), que era exactamente el reporte
     * del usuario: "tiene sin pagar muchas quincenas y tiene los mismos 6
     * puntos desde el primer pago anticipado".
     */
    private function applyLatePenaltyToPoints(CutoffRelation $relation): void
    {
        $distributor = $relation->distributor()->with(['category', 'branch.branchSetting'])->first();

        if ($distributor === null) {
            return;
        }

        $currentPoints = (float) $distributor->current_points;

        if ($currentPoints <= 0) {
            return;
        }

        $branchSetting = $distributor->branch?->branchSetting;

        $penaltyPercentage = (float) ($distributor->category?->late_penalty_percentage
            ?? $branchSetting?->late_penalty_percentage
            ?? PointSetting::query()->value('late_penalty_percentage')
            ?? 20.0);

        $pointsToRemove = (int) floor($currentPoints * ($penaltyPercentage / 100));

        if ($pointsToRemove <= 0) {
            return;
        }

        $distributor->decrement('current_points', $pointsToRemove);

        PointMovement::query()->create([
            'distributor_id' => $distributor->id,
            'cutoff_id' => $relation->cutoff_id,
            'transaction_type' => PointMovementType::PENALIZACION_ATRASO,
            'points' => -$pointsToRemove,
            'point_value_snapshot' => $branchSetting?->point_value_mxn ?? 2.00,
            'reason' => "Corte {$relation->relation_number} vencido sin pagar (-{$penaltyPercentage}% de puntos).",
            'transaction_date' => now(),
        ]);
    }
}
