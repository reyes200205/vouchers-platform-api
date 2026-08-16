<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VoucherStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Distributor;
use App\Models\FinancialProduct;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
final class VoucherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voucher_number' => fake()->unique()->numerify('V-#######'),
            'distributor_id' => Distributor::factory(),
            'customer_id' => Customer::factory(),
            'financial_product_id' => FinancialProduct::factory(),
            'branch_id' => Branch::factory(),
            'status' => VoucherStatus::APROBADO,
            'is_pre_vale' => false,
            'amount' => 15000.00,
            'company_commission_percentage_snapshot' => 10.0000,
            'company_commission_amount' => 1500.00,
            'insurance_amount_snapshot' => 100.00,
            'interest_percentage_snapshot' => 5.0000,
            'interest_amount' => 6000.00,
            'distributor_profit_percentage_snapshot' => 8.0000,
            'distributor_profit_amount' => 1200.00,
            'late_fee_amount_snapshot' => 300.00,
            'total_debt_amount' => 22600.00,
            'fortnightly_payment_amount' => 2825.00,
            'total_fortnights' => 8,
            'payments_made' => 0,
            'current_balance' => 22600.00,
            'is_canceled' => false,
        ];
    }
}