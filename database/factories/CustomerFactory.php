<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
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
            'customer_code' => fake()->unique()->numerify('CLI-#######'),
            'status' => CustomerStatus::EN_VERIFICACION,
            'bank_account' => fake()->numerify('##########'),
            'bank_clabe' => fake()->numerify('##################'),
            'account_holder_name' => fake()->name(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CustomerStatus::ACTIVO,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CustomerStatus::ACTIVO,
            'verified_at' => now(),
            'verified_by_user_id' => null,
        ]);
    }
}