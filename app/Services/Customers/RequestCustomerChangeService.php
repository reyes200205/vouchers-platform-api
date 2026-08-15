<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Enums\ChangeRequestStatus;
use App\Models\Customer;
use App\Models\CustomerChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RequestCustomerChangeService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, Customer $customer, array $data): CustomerChangeRequest
    {
        return DB::transaction(static function () use ($user, $customer, $data): CustomerChangeRequest {
            $oldValues = $customer->person->only([
                'first_name',
                'middle_name',
                'last_name',
                'second_last_name',
                'curp',
                'rfc',
                'home_phone',
                'mobile_phone',
                'email',
                'street',
                'external_number',
                'neighborhood',
                'city',
                'state',
                'postal_code',
            ]);

            $changeType = $data['change_type'];

            if ($changeType === 'IDENTITY') {
                $newValues = array_intersect_key($data['new_values'], array_flip([
                    'first_name',
                    'middle_name',
                    'last_name',
                    'second_last_name',
                    'curp',
                    'rfc',
                ]));
            } elseif ($changeType === 'CONTACT') {
                $newValues = array_intersect_key($data['new_values'], array_flip([
                    'home_phone',
                    'mobile_phone',
                    'email',
                    'street',
                    'external_number',
                    'neighborhood',
                    'city',
                    'state',
                    'postal_code',
                ]));
            } else {
                $newValues = [];
            }

            return CustomerChangeRequest::create([
                'customer_id' => $customer->id,
                'requested_by_user_id' => $user->id,
                'change_type' => $changeType,
                'old_values_json' => $oldValues,
                'new_values_json' => $newValues,
                'evidence_json' => $data['evidence'] ?? null,
                'status' => ChangeRequestStatus::PENDIENTE,
            ]);
        });
    }
}