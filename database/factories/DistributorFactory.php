<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DistributorStatus;
use App\Models\Branch;
use App\Models\Distributor;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Distributor>
 */
final class DistributorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'branch_id' => Branch::factory(),
            'distributor_number' => fake()->unique()->numerify('DIST-#######'),
            'status' => DistributorStatus::ACTIVA,
            'credit_limit' => 20000,
            'available_credit' => 20000,
            'unlimited_credit' => false,
            'current_points' => 0,
            'can_issue_vouchers' => true,
            'is_external' => false,
            'activated_at' => now(),
        ];
    }
}