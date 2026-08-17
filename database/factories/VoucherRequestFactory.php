<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VoucherRequestStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Distributor;
use App\Models\FinancialProduct;
use App\Models\User;
use App\Models\VoucherRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoucherRequest>
 */
final class VoucherRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'distributor_id' => Distributor::factory(),
            'customer_id' => Customer::factory(),
            'financial_product_id' => FinancialProduct::factory(),
            'branch_id' => Branch::factory(),
            'requested_amount' => 15000.00,
            'is_pre_vale' => false,
            'status' => VoucherRequestStatus::PENDIENTE,
            'snapshot_json' => null,
            'created_by_user_id' => User::factory(),
        ];
    }
}