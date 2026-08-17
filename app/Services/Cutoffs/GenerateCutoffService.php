<?php

declare(strict_types=1);

namespace App\Services\Cutoffs;

use App\Enums\CutoffRelationStatus;
use App\Enums\CutoffStatus;
use App\Enums\CutoffType;
use App\Enums\PointMovementType;
use App\Models\Branch;
use App\Models\Cutoff;
use App\Models\CutoffRelation;
use App\Models\CutoffRelationItem;
use App\Models\CustomerPayment;
use App\Models\Distributor;
use App\Models\PointMovement;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class GenerateCutoffService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, Branch $branch, array $data): Cutoff
    {
        $periodStart = Carbon::parse($data['period_start'])->startOfDay();
        $periodEnd = Carbon::parse($data['period_end'])->endOfDay();

        if ($periodEnd->lt($periodStart)) {
            abort(422, 'El periodo final no puede ser anterior al periodo inicial.');
        }

        return DB::transaction(function () use ($branch, $periodStart, $periodEnd): Cutoff {
            $cutoff = Cutoff::query()->create([
                'branch_id' => $branch->id,
                'cutoff_type' => CutoffType::PAGOS,
                'base_day_of_month' => $periodEnd->day,
                'base_time' => $periodEnd->format('H:i:s'),
                'scheduled_at' => $periodEnd,
                'executed_at' => now(),
                'status' => CutoffStatus::EJECUTADO,
                'config_snapshot_json' => json_encode([
                    'voucher_amount_step' => $branch->branchSetting?->voucher_amount_step,
                    'pre_vale_max_percentage' => $branch->branchSetting?->pre_vale_max_percentage,
                    'pre_vale_tolerance_amount' => $branch->branchSetting?->pre_vale_tolerance_amount,
                    'point_value_mxn' => $branch->branchSetting?->point_value_mxn,
                ]),
            ]);

            $distributors = Distributor::query()
                ->where('branch_id', $branch->id)
                ->whereNull('deactivated_at')
                ->orderBy('id')
                ->get();

            foreach ($distributors as $distributor) {
                $this->generateRelation($cutoff, $distributor, $periodStart, $periodEnd);
            }

            return $cutoff->refresh();
        });
    }

    private function generateRelation(Cutoff $cutoff, Distributor $distributor, Carbon $periodStart, Carbon $periodEnd): void
    {
        $previousRelation = CutoffRelation::query()
            ->where('distributor_id', $distributor->id)
            ->whereIn('status', [CutoffRelationStatus::GENERADA, CutoffRelationStatus::PARCIAL, CutoffRelationStatus::VENCIDA])
            ->latest('id')
            ->first();

        $payments = CustomerPayment::query()
            ->where('distributor_id', $distributor->id)
            ->whereNull('reversed_at')
            ->whereBetween('payment_date', [$periodStart, $periodEnd])
            ->orderBy('voucher_id')
            ->get()
            ->groupBy('voucher_id');

        if ($previousRelation === null && $payments->isEmpty()) {
            return;
        }

        $relation = CutoffRelation::query()->create([
            'cutoff_id' => $cutoff->id,
            'distributor_id' => $distributor->id,
            'previous_relation_id' => $previousRelation?->id,
            'relation_number' => 'REL-' . $cutoff->id . '-' . $distributor->id,
            'payment_reference' => 'REF-' . strtoupper(substr(md5(uniqid((string) $distributor->id, true)), 0, 10)),
            'payment_due_date' => $periodEnd->copy()->addDays(15)->toDateString(),
            'early_payment_start_date' => $periodEnd->copy()->addDay()->toDateString(),
            'early_payment_end_date' => $periodEnd->copy()->addDays(10)->toDateString(),
            'credit_limit_snapshot' => $distributor->credit_limit,
            'available_credit_snapshot' => $distributor->available_credit,
            'points_snapshot' => $distributor->current_points,
            'status' => CutoffRelationStatus::GENERADA,
            'generated_at' => now(),
        ]);

        $totalPayment = 0.0;
        $totalCommission = 0.0;
        $totalLateFees = 0.0;
        $totalEarlyBonusPoints = 0.0;
        $totalLatePenaltyPoints = 0.0;

        foreach ($payments as $voucherId => $voucherPayments) {
            $voucher = Voucher::query()->find($voucherId);

            if ($voucher === null) {
                continue;
            }

            $paymentAmount = round($voucherPayments->sum(fn ($payment) => (float) $payment->amount), 2);
            $lateFee = $this->calculateLateFees($voucher, $voucherPayments);
            $commission = round($paymentAmount * ((float) $voucher->distributor_profit_percentage_snapshot / 100), 2);

            $basePoints = (int) floor($paymentAmount / 1200) * (int) ($voucher->distributor?->category?->points_per_1200 ?? 1);
            $bonusPoints = $this->calculateEarlyBonusPoints($voucher, $voucherPayments, $basePoints);
            $penaltyPoints = $this->calculateLatePenaltyPoints($voucher, $voucherPayments, $basePoints);

            if ($bonusPoints > 0) {
                PointMovement::query()->create([
                    'distributor_id' => $voucher->distributor_id,
                    'voucher_id' => $voucher->id,
                    'cutoff_id' => $cutoff->id,
                    'transaction_type' => PointMovementType::GANADO_ANTICIPADO,
                    'points' => $bonusPoints,
                    'point_value_snapshot' => $cutoff->branch->branchSetting?->point_value_mxn ?? 2.00,
                    'reason' => 'Pago anticipado del cliente en el corte.',
                    'transaction_date' => now(),
                ]);

                $totalEarlyBonusPoints += $bonusPoints;
            }

            if ($penaltyPoints > 0) {
                PointMovement::query()->create([
                    'distributor_id' => $voucher->distributor_id,
                    'voucher_id' => $voucher->id,
                    'cutoff_id' => $cutoff->id,
                    'transaction_type' => PointMovementType::PENALIZACION_ATRASO,
                    'points' => -$penaltyPoints,
                    'point_value_snapshot' => $cutoff->branch->branchSetting?->point_value_mxn ?? 2.00,
                    'reason' => 'Pago atrasado del cliente en el corte.',
                    'transaction_date' => now(),
                ]);

                $totalLatePenaltyPoints += $penaltyPoints;
            }

            CutoffRelationItem::query()->create([
                'cutoff_relation_id' => $relation->id,
                'voucher_id' => $voucher->id,
                'customer_id' => $voucher->customer_id,
                'product_name_snapshot' => $voucher->financialProduct?->name ?? 'Producto',
                'payments_made' => $voucherPayments->count(),
                'total_payments' => $voucher->total_fortnights,
                'is_late_payment' => $lateFee > 0,
                'installment_number' => $voucher->payments_made,
                'accumulated_late_installments' => $voucher->payments_made - $voucherPayments->filter(
                    fn ($payment) => $payment->payment_date->lte($voucher->payment_due_date)
                )->count(),
                'commission_amount' => $commission,
                'payment_amount' => $paymentAmount,
                'late_fee_amount' => $lateFee,
                'line_total_amount' => round($paymentAmount + $lateFee - $commission, 2),
            ]);

            $totalPayment += $paymentAmount;
            $totalCommission += $commission;
            $totalLateFees += $lateFee;
        }

        $carryover = 0.0;

        if ($previousRelation !== null) {
            $unpaidItems = $previousRelation->items()->get();

            foreach ($unpaidItems as $item) {
                $carryover += (float) $item->line_total_amount;

                CutoffRelationItem::query()->create([
                    'cutoff_relation_id' => $relation->id,
                    'voucher_id' => $item->voucher_id,
                    'customer_id' => $item->customer_id,
                    'product_name_snapshot' => $item->product_name_snapshot,
                    'payments_made' => $item->payments_made,
                    'total_payments' => $item->total_payments,
                    'is_late_payment' => false,
                    'installment_number' => $item->installment_number,
                    'accumulated_late_installments' => $item->accumulated_late_installments,
                    'commission_amount' => 0.00,
                    'payment_amount' => $item->line_total_amount,
                    'late_fee_amount' => 0.00,
                    'line_total_amount' => $item->line_total_amount,
                    'previous_paid_amount' => 0.00,
                    'origin_cutoff_id' => $item->origin_cutoff_id ?? $previousRelation->cutoff_id,
                    'origin_relation_id' => $item->origin_relation_id ?? $previousRelation->id,
                ]);
            }

            $previousRelation->update([
                'status' => CutoffRelationStatus::CERRADA,
                'closed_by_carryover_at' => now(),
            ]);
        }

        $relation->update([
            'total_payment' => round($totalPayment, 2),
            'total_commission' => round($totalCommission, 2),
            'total_late_fees' => round($totalLateFees, 2),
            'total_carryover_received' => round($carryover, 2),
            'total_amount_due' => round($totalPayment + $totalLateFees - $totalCommission + $carryover, 2),
        ]);

        $pointsDelta = $totalEarlyBonusPoints - $totalLatePenaltyPoints;

        if ($pointsDelta !== 0.0) {
            $distributor->increment('current_points', $pointsDelta);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CustomerPayment>  $payments
     */
    private function calculateLateFees(Voucher $voucher, mixed $payments): float
    {
        $lateFee = 0.0;
        $dueDate = $voucher->payment_due_date;

        if ($dueDate === null) {
            return $lateFee;
        }

        foreach ($payments as $payment) {
            if ($payment->payment_date->gt(Carbon::parse($dueDate)->endOfDay())) {
                $lateFee += (float) $voucher->late_fee_amount_snapshot;
            }
        }

        return round($lateFee, 2);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CustomerPayment>  $payments
     */
    private function calculateEarlyBonusPoints(Voucher $voucher, mixed $payments, int $basePoints): int
    {
        if ($voucher->early_payment_start_date === null || $voucher->early_payment_end_date === null) {
            return 0;
        }

        $start = Carbon::parse($voucher->early_payment_start_date)->startOfDay();
        $end = Carbon::parse($voucher->early_payment_end_date)->endOfDay();
        $earlyAmount = 0.0;

        foreach ($payments as $payment) {
            if ($payment->payment_date->between($start, $end)) {
                $earlyAmount += (float) $payment->amount;
            }
        }

        if ($earlyAmount <= 0) {
            return 0;
        }

        $earlyBasePoints = (int) floor($earlyAmount / 1200) * (int) ($voucher->distributor?->category?->points_per_1200 ?? 1);

        return max(1, (int) round($earlyBasePoints * 0.10));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CustomerPayment>  $payments
     */
    private function calculateLatePenaltyPoints(Voucher $voucher, mixed $payments, int $basePoints): int
    {
        if ($voucher->payment_due_date === null) {
            return 0;
        }

        $dueDate = Carbon::parse($voucher->payment_due_date)->endOfDay();
        $lateAmount = 0.0;

        foreach ($payments as $payment) {
            if ($payment->payment_date->gt($dueDate)) {
                $lateAmount += (float) $payment->amount;
            }
        }

        if ($lateAmount <= 0) {
            return 0;
        }

        return (int) floor($lateAmount / 1200) * (int) ($voucher->distributor?->category?->points_per_1200 ?? 1);
    }
}