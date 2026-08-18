<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PointMovementType;
use App\Enums\VoucherStatus;
use App\Models\CustomerPayment;
use App\Models\Distributor;
use App\Models\PointMovement;
use App\Models\PointSetting;
use App\Models\User;
use App\Models\Voucher;
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

        return DB::transaction(function () use ($user, $voucher, $data, $amount, $currentBalance, $paymentDate, $affectsPoints): CustomerPayment {
            $payment = CustomerPayment::query()->create([
                'voucher_id' => $voucher->id,
                'customer_id' => $voucher->customer_id,
                'distributor_id' => $voucher->distributor_id,
                'collected_by_user_id' => $user->id,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? 'EFECTIVO',
                'is_partial' => $amount < (float) $voucher->current_balance,
                'affects_points' => $affectsPoints,
                'notes' => $data['notes'] ?? null,
            ]);

            $remaining = round($currentBalance - $amount, 2);

            $voucher->update([
                'payments_made' => $voucher->payments_made + 1,
                'current_balance' => $remaining,
                'status' => $remaining <= 0.005 ? VoucherStatus::PAGADO : VoucherStatus::PAGO_PARCIAL,
            ]);

            if ($affectsPoints) {
                $points = $this->calculatePoints($voucher->distributor, $amount);

                if ($points > 0) {
                    PointMovement::query()->create([
                        'distributor_id' => $voucher->distributor_id,
                        'voucher_id' => $voucher->id,
                        'customer_payment_id' => $payment->id,
                        'transaction_type' => PointMovementType::GANADO_PUNTUAL,
                        'points' => $points,
                        'point_value_snapshot' => $voucher->distributor->branch->branchSetting?->point_value_mxn ?? 2.00,
                        'reason' => 'Pago de cliente registrado.',
                        'transaction_date' => $paymentDate,
                    ]);

                    $voucher->distributor->increment('current_points', $points);
                }
            }

            return $payment->refresh();
        });
    }

    private function calculatePoints(Distributor $distributor, float $amount): int
    {
        $divisor = (int) (PointSetting::query()->value('point_divisor_factor') ?? 1200);
        $multiplier = $distributor->category?->points_per_1200
            ?? (int) (PointSetting::query()->value('point_multiplier') ?? 3);

        return (int) floor($amount / $divisor) * $multiplier;
    }
}