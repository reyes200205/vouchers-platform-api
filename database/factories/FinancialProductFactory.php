<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DisbursementMethod;
use App\Models\FinancialProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialProduct>
 */
final class FinancialProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('PROD-#####'),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'principal_amount' => 15000.00,
            'number_of_fortnights' => 8,
            'company_commission_percentage' => 10.0000,
            'insurance_amount' => 100.00,
            'fortnightly_interest_percentage' => 5.0000,
            'late_fee_amount' => 300.00,
            'disbursement_method' => DisbursementMethod::TRANSFERENCIA,
            'is_active' => true,
        ];
    }
}