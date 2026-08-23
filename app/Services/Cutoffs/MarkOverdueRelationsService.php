<?php

declare(strict_types=1);

namespace App\Services\Cutoffs;

use App\Enums\CutoffRelationStatus;
use App\Enums\VoucherStatus;
use App\Models\CutoffRelation;
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
 * periodo — ver GenerateCutoffService.
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

            // Un item de arrastre que YA trae multa (porque la relación de la
            // que se arrastró ya se había vencido antes) no vuelve a
            // cobrarla aquí: la multa es una sola por vale, no una por cada
            // corte que sigue sin pagarse. payment_amount/line_total_amount
            // de un arrastre YA vienen con esa multa incluida desde que se
            // generó (ver GenerateCutoffService, que copia el monto final de
            // la relación anterior tal cual) -- sumarla otra vez aquí la
            // duplicaría cada vez que la deuda se vuelve a vencer sin
            // pagarse. Antes esto sí la duplicaba: un arrastre de $2,737
            // (que ya incluía $200 de multa) se recalculaba a $2,937 la
            // siguiente vez que su relación se vencía, sin haber cobrado
            // nada de más.
            $alreadyCarriesFee = $isCarryover && (float) $item->late_fee_amount > 0;

            if (! $alreadyCarriesFee) {
                // La multa se lee del snapshot inmutable que quedó grabado en el vale al
                // emitirlo (vouchers.late_fee_amount_snapshot), tomado en su momento del
                // producto financiero (financial_products.late_fee_amount), no de la
                // configuración actual de la sucursal ni del producto en vivo.
                $lateFee = round((float) ($voucher?->late_fee_amount_snapshot ?? 0.0), 2);

                // Un item de arrastre (origin_relation_id no nulo) que TODAVÍA no
                // traía multa (se arrastró mientras su relación seguía GENERADA,
                // sin haberse vencido todavía) ya trae en payment_amount el monto
                // exacto que se le debe a la sucursal de un periodo anterior -- no
                // viene de redondear una quincena, así que aquí sí se suma la
                // multa directo sobre eso, por primera y única vez.
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
                    $lineTotal = floor($grossPerFortnight + $lateFee);
                } else {
                    $lineTotal = floor((float) $item->payment_amount + $lateFee);
                }

                // payment_amount ya es la quincena COMPLETA (con la comisión de la
                // distribuidora incluida — ver FinancialCalculationService), así que no
                // hay que volver a sumarle la comisión aquí: nada más se pone la
                // comisión en 0 (ya no se la queda) y se le agrega la multa.
                $item->update([
                    'is_late_payment' => true,
                    'commission_amount' => 0.00,
                    'late_fee_amount' => $lateFee,
                    'line_total_amount' => $lineTotal,
                ]);
            }

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
    }
}
