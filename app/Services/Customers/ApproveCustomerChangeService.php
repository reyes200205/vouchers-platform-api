<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Enums\ChangeRequestStatus;
use App\Models\CustomerChangeRequest;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class ApproveCustomerChangeService
{
    /**
     * @param  array{decision: string, rejection_reason?: string|null}  $data
     */
    public function execute(User $user, CustomerChangeRequest $changeRequest, array $data): CustomerChangeRequest
    {
        return DB::transaction(static function () use ($user, $changeRequest, $data): CustomerChangeRequest {
            if ($changeRequest->status !== ChangeRequestStatus::PENDIENTE) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'La solicitud de cambio ya fue resuelta.');
            }

            if ($data['decision'] === 'REJECT') {
                $changeRequest->update([
                    'status' => ChangeRequestStatus::RECHAZADA,
                    'approved_by_user_id' => $user->id,
                    'rejection_reason' => $data['rejection_reason'] ?? null,
                ]);

                return $changeRequest;
            }

            $person = $changeRequest->customer?->person;

            if ($person === null) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'No se encontro la persona asociada al cliente.');
            }

            $newValues = (array) $changeRequest->new_values_json;

            if (isset($newValues['curp']) && Person::query()->where('curp', $newValues['curp'])->where('id', '!=', $person->id)->exists()) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'La CURP ya esta en uso por otra persona.');
            }

            if (isset($newValues['rfc']) && Person::query()->where('rfc', $newValues['rfc'])->where('id', '!=', $person->id)->exists()) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'El RFC ya esta en uso por otra persona.');
            }

            foreach ($newValues as $field => $value) {
                $person->{$field} = $value;
            }

            $person->save();

            $changeRequest->update([
                'status' => ChangeRequestStatus::APROBADA,
                'approved_by_user_id' => $user->id,
                'applied_at' => now(),
            ]);

            return $changeRequest;
        });
    }
}
