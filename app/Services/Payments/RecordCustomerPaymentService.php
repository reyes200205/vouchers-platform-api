<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PointMovementType;
use App\Enums\VoucherStatus;
use App\Models\BranchSetting;
use App\Models\CustomerPayment;
use App\Models\Distributor;
use App\Models\PointMovement;
use App\Models\PointSetting;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class RecordCustomerPaymentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, Voucher $voucher, array $data): CustomerPayment
    {
        if ($voucher->status === VoucherStatus::PAGADO || $voucher->status === VoucherStatus::LIQUIDADO) {
            abort(422, 'El vale ya está pagado.');
        }

        if ($voucher->status === VoucherStatus::CANCELADO || $voucher->status === VoucherStatus::REVERSADO) {
            abort(422, 'El vale está cancelado y no admite pagos.');
        }

        $amount = (float) $data['amount'];
        $currentBalance = (float) $voucher->current_balance;

        if ($amount <= 0) {
            abort(422, 'El monto del pago debe ser mayor a cero.');
        }

        if ($amount > $currentBalance) {
            abort(422, "El pago excede el saldo pendiente del vale ($" . number_format($currentBalance, 2) . ').');
        }

        $voucher->load('distributor');

        $paymentDate = $data['payment_date'] ?? now()->toDateTimeString();
        $affectsPoints = (bool) ($data['affects_points'] ?? true);
        $points = 0;

        // Un pago es "fuera de tiempo" si se registra despues de la fecha de
        // vencimiento vigente del vale (quincena actual). Se captura ANTES de
        // avanzar payment_due_date mas abajo, y se guarda en due_date_snapshot
        // del pago para que el corte (GenerateCutoffService) pueda evaluarlo
        // despues sin depender de la fecha de vencimiento ya movida a la
        // siguiente quincena.
        $dueDateBeforePayment = $voucher->payment_due_date !== null
            ? Carbon::parse($voucher->payment_due_date)->endOfDay()
            : null;
        $isLate = $dueDateBeforePayment !== null && Carbon::parse($paymentDate)->gt($dueDateBeforePayment);

        // Los dias de la quincena los define la sucursal (branch_settings), nunca
        // el codigo: payment_due_days = a cuantos dias vence la siguiente quincena;
        // payment_frequency_days = hasta que dia del periodo el pago sigue contando
        // como "anticipado". Los defaults (15 y 14) solo aplican si la sucursal
        // nunca configuro nada.
        $branchSetting = BranchSetting::query()
            ->firstOrCreate(['branch_id' => $voucher->branch_id])
            ->refresh();
        $dueDays = (int) ($branchSetting->payment_due_days ?? 15);
        $frequencyDays = (int) ($branchSetting->payment_frequency_days ?? 14);

        return DB::transaction(function () use ($user, $voucher, $data, $amount, $currentBalance, $paymentDate, $affectsPoints, $isLate, $dueDateBeforePayment, $dueDays, $frequencyDays): CustomerPayment {
            $payment = CustomerPayment::query()->create([
                'voucher_id' => $voucher->id,
                'customer_id' => $voucher->customer_id,
                'distributor_id' => $voucher->distributor_id,
                'collected_by_user_id' => $user->id,
                'payment_date' => $paymentDate,
                'due_date_snapshot' => $dueDateBeforePayment?->toDateString(),
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? 'EFECTIVO',
                'is_partial' => $amount < (float) $voucher->current_balance,
                'affects_points' => $affectsPoints,
                'notes' => $data['notes'] ?? null,
            ]);

            $remaining = round($currentBalance - $amount, 2);

            // La siguiente quincena vence payment_due_days (config. de sucursal) despues
            // de la fecha de vencimiento actual (no de la fecha real del pago), para no
            // arrastrar el atraso o adelanto de este pago hacia el calendario de las
            // siguientes quincenas. La ventana de pago anticipado abre al dia siguiente
            // y cierra al llegar a payment_frequency_days.
            $nextDueDate = Carbon::parse($voucher->payment_due_date ?? $paymentDate)->addDays($dueDays);

            $voucher->update([
                'payments_made' => $voucher->payments_made + 1,
                'current_balance' => $remaining,
                'status' => $remaining <= 0.005 ? VoucherStatus::PAGADO : VoucherStatus::PAGO_PARCIAL,
                'payment_due_date' => $nextDueDate->toDateString(),
                'early_payment_start_date' => $nextDueDate->copy()->subDays($dueDays - 1)->toDateString(),
                'early_payment_end_date' => $nextDueDate->copy()->subDays($dueDays - $frequencyDays)->toDateString(),
            ]);

            if ($affectsPoints) {
                // Pago fuera de tiempo: se elimina un % del total de puntos. El % lo
                // define la categoria de la distribuidora si tiene override, si no el
                // valor global de point_settings (por defecto 20%, configurable por un admin).
                $penaltyPercentage = $isLate
                    ? (float) ($voucher->distributor->category?->late_penalty_percentage
                        ?? PointSetting::query()->value('late_penalty_percentage')
                        ?? 20.0)
                    : 0.0;

                $points = $this->calculatePoints($voucher->distributor, $amount, $penaltyPercentage);

                if ($points > 0) {
                    PointMovement::query()->create([
                        'distributor_id' => $voucher->distributor_id,
                        'voucher_id' => $voucher->id,
                        'customer_payment_id' => $payment->id,
                        'transaction_type' => PointMovementType::GANADO_PUNTUAL,
                        'points' => $points,
                        'point_value_snapshot' => $voucher->distributor->branch->branchSetting?->point_value_mxn ?? 2.00,
                        'reason' => $isLate
                            ? "Pago de cliente registrado (fuera de tiempo: -{$penaltyPercentage}% de puntos)."
                            : 'Pago de cliente registrado.',
                        'transaction_date' => $paymentDate,
                    ]);

                    $voucher->distributor->increment('current_points', $points);
                }
            }

            return $payment->refresh();
        });
    }

    private function calculatePoints(Distributor $distributor, float $amount, float $penaltyPercentage): int
    {
        $divisor = (int) (PointSetting::query()->value('point_divisor_factor') ?? 1200);
        $multiplier = $distributor->category?->points_per_1200
            ?? (int) (PointSetting::query()->value('point_multiplier') ?? 3);

        $basePoints = (int) floor($amount / $divisor) * $multiplier;

        if ($penaltyPercentage <= 0) {
            return $basePoints;
        }

        return (int) floor($basePoints * (1 - $penaltyPercentage / 100));
    }
}