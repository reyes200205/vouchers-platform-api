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
 * aplica a la distribuidora las consecuencias del atraso: multa/interés
 * (branch_settings, vía el snapshot del vale) sobre cada quincena vencida, y
 * se le quita la comisión que le tocaba por esas quincenas. Esto es lo que
 * después arrastra el siguiente corte como "quincena atrasada + multa" junto
 * (pero aparte) de la quincena normal del periodo — ver GenerateCutoffService.
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
                $this->applyOverdueCharges($relation);
            }

            return $relations->count();
        });
    }

    private function applyOverdueCharges(CutoffRelation $relation): void
    {
        $items = $relation->items()->get();
        $totalLateFees = 0.0;

        foreach ($items as $item) {
            $voucher = $item->voucher_id !== null ? Voucher::query()->find($item->voucher_id) : null;

            // La multa real vive en branch_settings.late_payment_penalty_amount;
            // el vale solo trae el snapshot inmutable de ese valor al momento en
            // que se emitió, nunca un monto propio del producto.
            $lateFee = round((float) ($voucher?->late_fee_amount_snapshot ?? 0.0), 2);

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
