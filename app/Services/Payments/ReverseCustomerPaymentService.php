<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PointMovementType;
use App\Enums\VoucherStatus;
use App\Models\CustomerPayment;
use App\Models\PointMovement;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

final class ReverseCustomerPaymentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, CustomerPayment $payment, array $data): CustomerPayment
    {
        if ($payment->reversed_at !== null) {
            abort(422, 'El pago ya fue reversado.');
        }

        $voucher = $payment->voucher;

        if ($voucher->status === VoucherStatus::LIQUIDADO) {
            abort(422, 'El vale ya fue liquidado y no admite reversos.');
        }

        return DB::transaction(function () use ($user, $payment, $voucher, $data): CustomerPayment {
            $payment->update([
                'reversed_at' => now(),
                'reversed_by_user_id' => $user->id,
                'reversal_reason' => $data['reason'] ?? null,
            ]);

            $remaining = round((float) $voucher->current_balance + (float) $payment->amount, 2);

            $voucher->update([
                'payments_made' => max(0, $voucher->payments_made - 1),
                'current_balance' => $remaining,
                'status' => $remaining <= 0.005 ? VoucherStatus::PAGADO : VoucherStatus::PAGO_PARCIAL,
            ]);

            $movement = $payment->pointMovements()->where('transaction_type', PointMovementType::GANADO_PUNTUAL)->first();

            if ($movement !== null) {
                PointMovement::query()->create([
                    'distributor_id' => $voucher->distributor_id,
                    'voucher_id' => $voucher->id,
                    'customer_payment_id' => $payment->id,
                    'transaction_type' => PointMovementType::REVERSO,
                    'points' => -$movement->points,
                    'point_value_snapshot' => $movement->point_value_snapshot,
                    'reason' => 'Reverso de pago: ' . ($data['reason'] ?? 'Sin motivo'),
                    'transaction_date' => now(),
                ]);

                $voucher->distributor()->decrement('current_points', $movement->points);
            }

            return $payment->refresh();
        });
    }
}