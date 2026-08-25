<?php

declare(strict_types=1);

namespace App\Services\Vouchers;

use App\Enums\VoucherRequestStatus;
use App\Models\User;
use App\Models\VoucherRequest;
use Illuminate\Support\Facades\DB;

/**
 * Rechaza una solicitud de vale (pre-issue) pendiente. El credito disponible
 * ya se aparto desde que la distribuidora mando la solicitud (ver
 * RequestVoucherService), asi que rechazar tiene que devolverselo -- si no,
 * se quedaria descontado para siempre por un vale que nunca se va a otorgar.
 */
final class RejectVoucherRequestService
{
    public function execute(User $user, VoucherRequest $voucherRequest, string $rejectionReason): VoucherRequest
    {
        return DB::transaction(function () use ($user, $voucherRequest, $rejectionReason): VoucherRequest {
            if ($voucherRequest->status !== VoucherRequestStatus::PENDIENTE) {
                abort(422, 'La solicitud ya fue resuelta.');
            }

            $voucherRequest->update([
                'status' => VoucherRequestStatus::RECHAZADO,
                'rejection_reason' => $rejectionReason,
                'decided_by_user_id' => $user->id,
                'decided_at' => now(),
            ]);

            $voucherRequest->distributor()->increment('available_credit', (float) $voucherRequest->requested_amount);

            return $voucherRequest;
        });
    }
}
