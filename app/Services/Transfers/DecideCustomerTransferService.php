<?php

declare(strict_types=1);

namespace App\Services\Transfers;

use App\Enums\CustomerDistributorRelationshipStatus;
use App\Enums\CustomerTransferRequestStatus;
use App\Models\CustomerDistributor;
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
                    'status' => CustomerTransferRequestStatus::RECHAZADA,
                    'coordinator_user_id' => $user->id,
                    'comments' => $data['comments'] ?? null,
                    'rejection_reason' => $data['rejection_reason'] ?? null,
                ]);

                return $transferRequest;
            }

            $currentLink = CustomerDistributor::query()
                ->where('customer_id', $transferRequest->customer_id)
                ->where('distributor_id', $transferRequest->source_distributor_id)
                ->where('relationship_status', CustomerDistributorRelationshipStatus::ACTIVA->value)
                ->firstOrFail();

            $currentLink->update([
                'relationship_status' => CustomerDistributorRelationshipStatus::TERMINADA,
                'unlinked_at' => now(),
            ]);

            CustomerDistributor::updateOrCreate(
                [
                    'distributor_id' => $transferRequest->destination_distributor_id,
                    'customer_id' => $transferRequest->customer_id,
                ],
                [
                    'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
                    'prevale_approved' => false,
                    'blocked_due_to_relationship' => false,
                    'linked_at' => now(),
                ]
            );

            $transferRequest->update([
                'status' => CustomerTransferRequestStatus::EJECUTADA,
                'coordinator_user_id' => $user->id,
                'comments' => $data['comments'] ?? null,
                'executed_at' => now(),
            ]);

            return $transferRequest;
        });
    }
}