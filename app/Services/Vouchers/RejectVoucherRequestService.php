<?php

declare(strict_types=1);

namespace App\Services\Vouchers;

use App\Enums\VoucherRequestStatus;
use App\Models\User;
use App\Models\VoucherRequest;
use Illuminate\Support\Facades\DB;

/**
 * Rechaza una solicitud de vale (pre-issue) pendiente. A diferencia de la
 * aprobacion, no hay credito que revertir: el credito disponible de la
 * distribuidora solo se descuenta al aprobar (ver ApproveVoucherService),
 * asi que rechazar es simplemente marcar el estado con el motivo.
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

            return $voucherRequest;
        });
    }
}
