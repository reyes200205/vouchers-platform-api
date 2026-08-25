<?php

declare(strict_types=1);

namespace App\Services\Transfers;

use App\Enums\CustomerDistributorRelationshipStatus;
use App\Enums\CustomerTransferRequestStatus;
use App\Enums\VoucherRequestStatus;
use App\Enums\VoucherStatus;
use App\Models\CustomerDistributor;
use App\Models\CustomerTransferRequest;
use App\Models\User;
use App\Models\VoucherRequest;
use Illuminate\Support\Facades\DB;

final class FinalizeCustomerTransferService
{
    public function execute(User $user, CustomerTransferRequest $transferRequest): CustomerTransferRequest
    {
        return DB::transaction(static function () use ($user, $transferRequest): CustomerTransferRequest {
            if ($transferRequest->status !== CustomerTransferRequestStatus::AUTORIZADA) {
                abort(422, 'La transferencia todavía no está autorizada por el coordinador.');
            }

            $destinationDistributor = $transferRequest->destinationDistributor;

            if ($destinationDistributor->person_id !== $user->person_id) {
                abort(422, 'Solo la distribuidora destino puede aceptar al cliente.');
            }

            $customer = $transferRequest->customer;

            // Revalidacion defensiva: la distribuidora origen pudo haberle
            // dado un vale nuevo al cliente (o pedido uno) mientras la
            // solicitud esperaba autorizacion del coordinador.
            $hasPendingRequest = VoucherRequest::query()
                ->where('customer_id', $transferRequest->customer_id)
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

            $currentLink = CustomerDistributor::query()
                ->where('customer_id', $transferRequest->customer_id)
                ->where('distributor_id', $transferRequest->source_distributor_id)
                ->where('relationship_status', CustomerDistributorRelationshipStatus::ACTIVA->value)
                ->firstOrFail();

            $currentLink->update([
                'relationship_status' => CustomerDistributorRelationshipStatus::TERMINADA,
                'unlinked_at' => now(),
            ]);

            // prevale_approved en true: el cliente ya tiene historial de
            // vales (o al menos ya fue dado de alta y verificado con su
            // distribuidora anterior), asi que no debe volver a topar su
            // primer vale con la nueva distribuidora al 50% del credito
            // disponible (ver RequestVoucherService::execute).
            CustomerDistributor::updateOrCreate(
                [
                    'distributor_id' => $transferRequest->destination_distributor_id,
                    'customer_id' => $transferRequest->customer_id,
                ],
                [
                    'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
                    'prevale_approved' => true,
                    'blocked_due_to_relationship' => false,
                    'linked_at' => now(),
                ]
            );

            $transferRequest->update([
                'status' => CustomerTransferRequestStatus::EJECUTADA,
                'finalized_by_user_id' => $user->id,
                'executed_at' => now(),
            ]);

            return $transferRequest;
        });
    }
}
