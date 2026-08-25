<?php

declare(strict_types=1);

namespace App\Services\Transfers;

use App\Enums\CustomerTransferRequestStatus;
use App\Models\CustomerTransferRequest;
use App\Models\User;
use App\Notifications\CustomerTransferAwaitingCoordinatorNotification;
use Illuminate\Support\Facades\DB;

final class DecideDestinationTransferService
{
    /**
     * @param  array{decision: string, rejection_reason?: string|null}  $data
     */
    public function execute(User $user, CustomerTransferRequest $transferRequest, array $data): CustomerTransferRequest
    {
        return DB::transaction(static function () use ($user, $transferRequest, $data): CustomerTransferRequest {
            if ($transferRequest->status !== CustomerTransferRequestStatus::PENDIENTE_DESTINO) {
                abort(422, 'La solicitud de transferencia ya fue resuelta.');
            }

            $destinationDistributor = $transferRequest->destinationDistributor;

            if ($destinationDistributor->person_id !== $user->person_id) {
                abort(422, 'Solo la distribuidora destino puede responder esta solicitud.');
            }

            if ($data['decision'] === 'REJECT') {
                $transferRequest->update([
                    'status' => CustomerTransferRequestStatus::RECHAZADA_DESTINO,
                    'destination_decided_by_user_id' => $user->id,
                    'destination_decided_at' => now(),
                    'rejection_reason' => $data['rejection_reason'] ?? null,
                ]);

                return $transferRequest;
            }

            $transferRequest->update([
                'status' => CustomerTransferRequestStatus::PENDIENTE_COORDINADOR,
                'destination_decided_by_user_id' => $user->id,
                'destination_decided_at' => now(),
            ]);

            self::notifyCoordinators($transferRequest);

            return $transferRequest;
        });
    }

    private static function notifyCoordinators(CustomerTransferRequest $transferRequest): void
    {
        $branchId = $transferRequest->sourceDistributor?->branch_id;

        if ($branchId === null) {
            return;
        }

        $coordinators = User::query()
            ->whereHas('businessRoles', function ($query) use ($branchId): void {
                $query->where('roles.name', 'coordinator')
                    ->where('model_has_roles.branch_id', $branchId);
            })
            ->get();

        foreach ($coordinators as $coordinator) {
            $coordinator->notify(new CustomerTransferAwaitingCoordinatorNotification($transferRequest));
        }
    }
}
