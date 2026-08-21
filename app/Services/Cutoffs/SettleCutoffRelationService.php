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

/**
 * Se ejecuta cuando una CutoffRelation (deuda de una distribuidora con la
 * sucursal en un corte) queda PAGADA — vía conciliación automática
 * (AutoMatchDepositsService) o verificación manual del gerente
 * (VerifyReconciliationService). Nunca se dispara desde un pago individual
 * de cliente: eso ya no existe como fuente de verdad.
 *
 * Hace dos cosas, ambas a nivel de la relación (no del cliente):
 *  1. Avanza cada vale detrás de la relación (payments_made/current_balance/
 *     payment_due_date), una vez por cada item de la relación — un vale puede
 *     tener dos items en el mismo corte (su quincena normal + el arrastre de
 *     una quincena atrasada), y en ese caso avanza dos quincenas de una vez.
 *  2. Otorga los puntos a la distribuidora (nunca al cliente), aplicando el
 *     -20% (configurable) si la relación llegó a tener multa por atraso, o el
 *     bono por conciliación anticipada si se resolvió dentro de la ventana.
 */
final class SettleCutoffRelationService
{
    public function execute(CutoffRelation $relation): void
    {
        if ($relation->status !== CutoffRelationStatus::PAGADA) {
            return;
        }

        $this->advanceVouchers($relation);
        $this->awardPoints($relation);
    }

    private function advanceVouchers(CutoffRelation $relation): void
    {
        $items = $relation->items()->get();

        foreach ($items as $item) {
            if ($item->voucher_id === null) {
                continue;
            }

            $voucher = Voucher::query()->find($item->voucher_id);

            if ($voucher === null) {
                continue;
            }

            if (in_array($voucher->status, [VoucherStatus::PAGADO, VoucherStatus::LIQUIDADO, VoucherStatus::CANCELADO, VoucherStatus::REVERSADO], true)) {
                continue;
            }

            $paid = (float) $item->payment_amount;
            $remaining = round((float) $voucher->current_balance - $paid, 2);
            $paymentsMade = $voucher->payments_made + 1;

            $branchSetting = $voucher->branch?->branchSetting;
            $dueDays = (int) ($branchSetting?->payment_due_days ?? 15);
            $frequencyDays = (int) ($branchSetting?->payment_frequency_days ?? 14);
            $nextDueDate = Carbon::parse($voucher->payment_due_date ?? now())->addDays($dueDays);

            $isSettled = $remaining <= 0.005 || $paymentsMade >= $voucher->total_fortnights;

            $voucher->update([
                'payments_made' => $paymentsMade,
                'current_balance' => max($remaining, 0.0),
                'status' => $isSettled ? VoucherStatus::PAGADO : VoucherStatus::ACTIVO,
                'payment_due_date' => $nextDueDate->toDateString(),
                'early_payment_start_date' => $nextDueDate->copy()->subDays($dueDays - 1)->toDateString(),
                'early_payment_end_date' => $nextDueDate->copy()->subDays($dueDays - $frequencyDays)->toDateString(),
            ]);

            // El crédito disponible de la distribuidora se descontó por el
            // PRINCIPAL del vale al aprobarlo (ver ApproveVoucherService) -- no
            // se va liberando quincena a quincena, porque cada pago trae mezclado
            // interés/seguro/comisión y no hay forma de saber qué parte de eso es
            // "principal ya recuperado". Se libera completo, de una sola vez,
            // hasta que el vale termina de pagarse por completo.
            if ($isSettled) {
                $voucher->distributor()->increment('available_credit', (float) $voucher->amount);
            }
        }
    }

    private function awardPoints(CutoffRelation $relation): void
    {
        $distributor = $relation->distributor()->with(['category', 'branch.branchSetting'])->first();

        if ($distributor === null) {
            return;
        }

        $amount = (float) $relation->total_payment;

        if ($amount <= 0) {
            return;
        }

        // La fórmula de puntos la configura cada sucursal (branch_settings),
        // igual que sus demás reglas de negocio — no un numero fijo en el
        // código. point_settings (global) solo queda como el default del
        // sistema para cuando una sucursal no configuró su propio valor.
        $branchSetting = $distributor->branch?->branchSetting;

        $divisor = (int) ($branchSetting?->point_divisor_factor
            ?? PointSetting::query()->value('point_divisor_factor')
            ?? 1200);
        $multiplier = (int) ($distributor->category?->points_per_1200
            ?? $branchSetting?->point_multiplier
            ?? PointSetting::query()->value('point_multiplier')
            ?? 3);

        $basePoints = (int) floor($amount / $divisor) * $multiplier;

        if ($basePoints <= 0) {
            return;
        }

        // La fórmula (total / divisor, piso, * multiplicador) es la fórmula de
        // puntos para pagos anticipados — no hay un bono extra aparte, solo se
        // le "quita" un % cuando llega fuera de tiempo. Una relación "fuera de
        // tiempo" es la que en algún momento llegó a vencerse y acumuló multa
        // (MarkOverdueRelationsService); ese es el único lugar donde se decide
        // si un corte llegó tarde, para no repetir esa lógica de fechas en más
        // de un servicio. Esto son puntos, no dinero: point_value_mxn solo se
        // guarda como referencia de a cuánto equivale cada punto al canjearlo.
        $wasLate = ((float) $relation->total_late_fees) > 0;

        if ($wasLate) {
            $penaltyPercentage = (float) ($distributor->category?->late_penalty_percentage
                ?? $branchSetting?->late_penalty_percentage
                ?? PointSetting::query()->value('late_penalty_percentage')
                ?? 20.0);

            $points = (int) floor($basePoints * (1 - $penaltyPercentage / 100));
            $type = PointMovementType::GANADO_PUNTUAL;
            $reason = "Corte conciliado fuera de tiempo (-{$penaltyPercentage}% de puntos).";
        } else {
            $points = $basePoints;
            $type = PointMovementType::GANADO_ANTICIPADO;
            $reason = 'Corte conciliado a tiempo (pago anticipado).';
        }

        if ($points <= 0) {
            return;
        }

        PointMovement::query()->create([
            'distributor_id' => $distributor->id,
            'cutoff_id' => $relation->cutoff_id,
            'transaction_type' => $type,
            'points' => $points,
            'point_value_snapshot' => $branchSetting?->point_value_mxn ?? 2.00,
            'reason' => $reason,
            'transaction_date' => now(),
        ]);

        $distributor->increment('current_points', $points);
    }
}
