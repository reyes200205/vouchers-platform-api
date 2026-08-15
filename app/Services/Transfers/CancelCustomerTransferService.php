<?php

declare(strict_types=1);

namespace App\Services\Transfers;

use App\Enums\CustomerTransferRequestStatus;
use App\Models\CustomerTransferRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CancelCustomerTransferService
{
    public function execute(User $user, CustomerTransferRequest $transferRequest): CustomerTransferRequest
    {
        return DB::transaction(static function () use ($user, $transferRequest): CustomerTransferRequest {
            if ($transferRequest->status !== CustomerTransferRequestStatus::PENDIENTE_COORDINADOR) {
                abort(422, 'Solo puede cancelarse una solicitud pendiente de decisión.');
            }

            if ($transferRequest->requested_by_user_id !== $user->id) {
                abort(422, 'Solo la distribuidora que inició la solicitud puede cancelarla.');
            }

            $transferRequest->update([
                'status' => CustomerTransferRequestStatus::CANCELADA,
            ]);

            return $transferRequest;
        });
    }
}