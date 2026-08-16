<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CreditIncreaseRequestStatus;
use App\Models\Branch;
use App\Models\CreditIncreaseRequest;
use App\Models\Distributor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditIncreaseRequest>
 */
final class CreditIncreaseRequestFactory extends Factory
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
            'branch_id' => Branch::factory(),
            'requested_by_user_id' => User::factory(),
            'requested_amount' => 10000.00,
            'reason' => null,
            'status' => CreditIncreaseRequestStatus::PENDIENTE,
        ];
    }
}