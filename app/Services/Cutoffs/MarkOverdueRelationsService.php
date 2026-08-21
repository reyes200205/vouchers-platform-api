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

        foreach ($items as $item) {
            $voucher = $item->voucher_id !== null ? Voucher::query()->find($item->voucher_id) : null;

            // La multa se lee del snapshot inmutable que quedó grabado en el vale al
            // emitirlo (vouchers.late_fee_amount_snapshot), tomado en su momento del
            // producto financiero (financial_products.late_fee_amount), no de la
            // configuración actual de la sucursal ni del producto en vivo.
            $lateFee = round((float) ($voucher?->late_fee_amount_snapshot ?? 0.0), 2);

            // payment_amount ya es la quincena COMPLETA (con la comisión de la
            // distribuidora incluida — ver FinancialCalculationService), así que no
            // hay que volver a sumarle la comisión aquí: nada más se pone la
            // comisión en 0 (ya no se la queda) y se le agrega la multa.
            $item->update([
                'is_late_payment' => true,
                'commission_amount' => 0.00,
                'late_fee_amount' => $lateFee,
                'line_total_amount' => round((float) $item->payment_amount + $lateFee, 2),
            ]);

            $totalLateFees += $lateFee;

            if ($voucher !== null && ! in_array($voucher->status, [
                VoucherStatus::PAGADO,
                VoucherStatus::LIQUIDADO,
                VoucherStatus::CANCELADO,
                VoucherStatus::REVERSADO,
            ], true)) {
                $voucher->update(['status' => VoucherStatus::MOROSO]);
            }
        }

        // Igual que por item: total_payment ya es la suma de las quincenas
        // completas (con comisión incluida), así que total_amount_due no debe
        // volver a sumar la comisión — solo se pone en 0 (ya no se la queda) y se
        // agrega la multa total.
        $relation->update([
            'status' => CutoffRelationStatus::VENCIDA,
            'total_late_fees' => round($totalLateFees, 2),
            'total_commission' => 0.00,
            'total_amount_due' => round(
                (float) $relation->total_payment + $totalLateFees + (float) $relation->total_carryover_received,
                2
            ),
        ]);
    }
}
