<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class VerifyCustomerService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, Customer $customer, array $data): Customer
    {
        return DB::transaction(static function () use ($user, $customer, $data): Customer {
            if ($customer->status === CustomerStatus::BLOQUEADO) {
                abort(422, 'El cliente está bloqueado y no puede verificarse.');
            }

            $customer->update([
                'status' => CustomerStatus::ACTIVO,
                'verified_at' => now(),
                'verified_by_user_id' => $user->id,
                'id_front_photo' => $data['id_front_photo'] ?? $customer->id_front_photo,
                'id_back_photo' => $data['id_back_photo'] ?? $customer->id_back_photo,
                'id_selfie_photo' => $data['id_selfie_photo'] ?? $customer->id_selfie_photo,
                'proof_of_address_photo' => $data['proof_of_address_photo'] ?? $customer->proof_of_address_photo,
            ]);

            return $customer;
        });
    }
}