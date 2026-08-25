<?php

declare(strict_types=1);

namespace App\Services\Transfers;

use App\Enums\CustomerTransferRequestStatus;
use App\Enums\CustomerDistributorRelationshipStatus;
use App\Enums\VoucherRequestStatus;
use App\Models\Customer;
use App\Models\CustomerTransferRequest;
use App\Models\Distributor;
use App\Models\User;
use App\Models\VoucherRequest;
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

            $sourceDistributor = $customer->customerDistributors()
                ->where('relationship_status', CustomerDistributorRelationshipStatus::ACTIVA->value)
                ->with('distributor')
                ->first()?->distributor;

            if ($sourceDistributor === null) {
                abort(422, 'El cliente no tiene una distribuidora de origen activa.');
            }

            if ($sourceDistributor->person_id !== $user->person_id) {
                abort(422, 'Solo la distribuidora que actualmente tiene al cliente puede iniciar la transferencia.');
            }

            if ($sourceDistributor->id === $destinationDistributor->id) {
                abort(422, 'La distribuidora destino ya es la distribuidora de origen del cliente.');
            }

            if ($customer->status === 'BLOQUEADO') {
                abort(422, 'El cliente está bloqueado y no puede transferirse.');
            }

            // Un vale recien pedido (PENDIENTE) todavia no es un Voucher -- no
            // existe hasta que el coordinador lo aprueba -- pero el cliente ya
            // no esta "en ceros" mientras esa solicitud siga viva, asi que
            // tambien bloquea la transferencia. Si la solicitud se rechaza o
            // se cancela deja de contar; si se aprueba, pasa a ser un Voucher
            // APROBADO y lo cubre el chequeo de abajo (que tambien deja de
            // aplicar solo si el vale vence y CancelExpiredVouchersService lo
            // cancela automaticamente).
            $hasPendingRequest = VoucherRequest::query()
                ->where('customer_id', $customer->id)
                ->where('status', VoucherRequestStatus::PENDIENTE->value)
                ->exists();

            if ($hasPendingRequest) {
                abort(422, 'El cliente tiene un vale pendiente de aprobación. Debe resolverse antes de poder transferirse.');
            }

            $hasDebt = $customer->vouchers()
                ->whereIn('status', [
                    VoucherStatus::APROBADO->value,
                    VoucherStatus::ACTIVO->value,
                    VoucherStatus::PAGO_PARCIAL->value,
                    VoucherStatus::MOROSO->value,
                ])
                ->where('current_balance', '>', 0)
                ->exists();

            if ($hasDebt) {
                abort(422, 'El cliente tiene un vale pendiente de ferear o con saldo activo. Debe liquidarlo o esperar a que venza antes de poder transferirse.');
            }

            $pendingExists = CustomerTransferRequest::query()
                ->where('customer_id', $customer->id)
                ->whereIn('status', [
                    CustomerTransferRequestStatus::PENDIENTE_DESTINO->value,
                    CustomerTransferRequestStatus::PENDIENTE_COORDINADOR->value,
                    CustomerTransferRequestStatus::AUTORIZADA->value,
                ])
                ->exists();

            if ($pendingExists) {
                abort(422, 'Ya existe una solicitud de transferencia en curso para este cliente.');
            }

            return CustomerTransferRequest::create([
                'customer_id' => $customer->id,
                'source_distributor_id' => $sourceDistributor->id,
                'destination_distributor_id' => $destinationDistributor->id,
                'requested_by_user_id' => $user->id,
                'status' => CustomerTransferRequestStatus::PENDIENTE_DESTINO,
                'request_reason' => $data['request_reason'] ?? null,
            ]);
        });
    }
}
