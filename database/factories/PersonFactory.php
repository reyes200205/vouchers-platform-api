<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
final class PersonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'second_last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(['M', 'F', 'OTHER']),
            'birth_date' => fake()->date(),
            'mobile_phone' => fake()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
            'street' => fake()->streetName(),
            'external_number' => fake()->buildingNumber(),
            'neighborhood' => fake()->citySuffix(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
        ];
    }
}
