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

            // El credito de la distribuidora no se toca al pedir el vale, solo al
            // aprobarse (ver RequestVoucherService/ApproveVoucherService), asi que
            // rechazar una solicitud pendiente no tiene nada que devolver.
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
