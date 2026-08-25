<?php

declare(strict_types=1);

namespace App\Services\Transfers;

use App\Enums\CustomerTransferRequestStatus;
use App\Models\CustomerTransferRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DecideCustomerTransferService
{
    /**
     * @param  array{decision: string, comments?: string|null, rejection_reason?: string|null}  $data
     */
    public function execute(User $user, CustomerTransferRequest $transferRequest, array $data): CustomerTransferRequest
    {
        return DB::transaction(static function () use ($user, $transferRequest, $data): CustomerTransferRequest {
            if ($transferRequest->status !== CustomerTransferRequestStatus::PENDIENTE_COORDINADOR) {
                abort(422, 'La solicitud de transferencia ya fue resuelta.');
            }

            if ($data['decision'] === 'REJECT') {
                $transferRequest->update([
                    'status' => CustomerTransferRequestStatus::RECHAZADA_COORDINADOR,
                    'coordinator_user_id' => $user->id,
                    'coordinator_decided_at' => now(),
                    'comments' => $data['comments'] ?? null,
                    'rejection_reason' => $data['rejection_reason'] ?? null,
                ]);

                return $transferRequest;
            }

            $transferRequest->update([
                'status' => CustomerTransferRequestStatus::AUTORIZADA,
                'coordinator_user_id' => $user->id,
                'coordinator_decided_at' => now(),
                'comments' => $data['comments'] ?? null,
            ]);

            return $transferRequest;
        });
    }
}
