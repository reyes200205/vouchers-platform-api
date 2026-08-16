<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Enums\CustomerDistributorRelationshipStatus;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\CustomerDistributor;
use App\Models\Distributor;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StoreCustomerService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): Customer
    {
        return DB::transaction(static function () use ($user, $data): Customer {
            $distributor = Distributor::query()
                ->where('person_id', $user->person_id)
                ->firstOrFail();

            $customerCode = 'CLI-' . strtoupper(Str::random(8));

            $person = Person::create($data['person']);

            $customer = Customer::create([
                'person_id' => $person->id,
                'branch_id' => $distributor->branch_id,
                'customer_code' => $customerCode,
                'status' => CustomerStatus::EN_VERIFICACION,
                'bank_account' => $data['bank_account'] ?? null,
                'bank_clabe' => $data['bank_clabe'] ?? null,
                'account_holder_name' => $data['account_holder_name'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            CustomerDistributor::create([
                'distributor_id' => $distributor->id,
                'customer_id' => $customer->id,
                'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
                'prevale_approved' => false,
                'blocked_due_to_relationship' => false,
                'linked_at' => now(),
            ]);

            return $customer;
        });
    }
}