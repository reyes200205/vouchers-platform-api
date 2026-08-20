<?php

declare(strict_types=1);

namespace App\Services\Vouchers;

use App\Enums\VoucherRequestStatus;
use App\Models\User;
use App\Models\VoucherRequest;
use Illuminate\Support\Facades\DB;

final class RejectVoucherService
{
    public function execute(User $user, VoucherRequest $voucherRequest, ?string $reason): VoucherRequest
    {
        return DB::transaction(function () use ($user, $voucherRequest, $reason): VoucherRequest {
            if ($voucherRequest->status !== VoucherRequestStatus::PENDIENTE) {
                abort(422, 'La solicitud ya fue resuelta.');
            }

            $voucherRequest->load('distributor');
            $snapshot = $voucherRequest->snapshot_json ?? [];
            $totalDebt = (float) ($snapshot['total_debt_amount'] ?? $voucherRequest->requested_amount);

            // Devuelve el credito reservado al pedir el vale (ver RequestVoucherService).
            $voucherRequest->distributor->increment('available_credit', $totalDebt);

            $voucherRequest->update([
                'status' => VoucherRequestStatus::RECHAZADO,
                'rejection_reason' => $reason,
                'decided_by_user_id' => $user->id,
                'decided_at' => now(),
            ]);

            return $voucherRequest;
        });
    }
}
