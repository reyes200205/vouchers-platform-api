<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DistributorCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DistributorCategory>
 */
final class DistributorCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('CAT-###'),
            'name' => fake()->unique()->words(2, true),
            'commission_percentage' => 8.0000,
            'points_per_1200' => 3,
            'late_penalty_percentage' => 20.0000,
            'is_active' => true,
        ];
    }
}