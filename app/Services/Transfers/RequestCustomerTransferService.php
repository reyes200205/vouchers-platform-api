<?php

declare(strict_types=1);

namespace App\Services\Transfers;

use App\Enums\CustomerTransferRequestStatus;
use App\Enums\CustomerDistributorRelationshipStatus;
use App\Models\Customer;
use App\Models\CustomerDistributor;
use App\Models\CustomerTransferRequest;
use App\Models\Distributor;
use App\Models\User;
use App\Enums\VoucherStatus;
use Illuminate\Support\Facades\DB;

final class RequestCustomerTransferService
{
    /**
     * @param  array{destination_distributor_id: int, request_reason?: string|null}  $data
     */
    public function execute(User $user, Customer $customer, array $data): CustomerTransferRequest
    {
        return DB::transaction(static function () use ($user, $customer, $data): CustomerTransferRequest {
            $destinationDistributor = Distributor::findOrFail($data['destination_distributor_id']);

            if ($destinationDistributor->person_id !== $user->person_id) {
                abort(422, 'Solo la distribuidora destino puede iniciar la transferencia.');
            }

            if ($customer->status === 'BLOQUEADO') {
                abort(422, 'El cliente está bloqueado y no puede transferirse.');
            }

            $hasDebt = $customer->vouchers()
                ->whereIn('status', [
                    VoucherStatus::ACTIVO->value,
                    VoucherStatus::PAGO_PARCIAL->value,
                    VoucherStatus::MOROSO->value,
                ])
                ->where('current_balance', '>', 0)
                ->exists();

            if ($hasDebt) {
                abort(422, 'El cliente tiene saldo pendiente y no puede transferirse.');
            }

            $sourceDistributor = $customer->customerDistributors()
                ->where('relationship_status', CustomerDistributorRelationshipStatus::ACTIVA->value)
                ->with('distributor')
                ->first()?->distributor;

            if ($sourceDistributor === null) {
                abort(422, 'El cliente no tiene una distribuidora de origen activa.');
            }

            if ($sourceDistributor->id === $destinationDistributor->id) {
                abort(422, 'La distribuidora destino ya es la distribuidora de origen del cliente.');
            }

            $pendingExists = CustomerTransferRequest::query()
                ->where('customer_id', $customer->id)
                ->where('status', CustomerTransferRequestStatus::PENDIENTE_COORDINADOR->value)
                ->exists();

            if ($pendingExists) {
                abort(422, 'Ya existe una solicitud de transferencia pendiente para este cliente.');
            }

            return CustomerTransferRequest::create([
                'customer_id' => $customer->id,
                'source_distributor_id' => $sourceDistributor->id,
                'destination_distributor_id' => $destinationDistributor->id,
                'requested_by_user_id' => $user->id,
                'status' => CustomerTransferRequestStatus::PENDIENTE_COORDINADOR,
                'request_reason' => $data['request_reason'] ?? null,
            ]);
        });
    }
}