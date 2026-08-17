<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PointRedemptionStatus;
use App\Models\Branch;
use App\Models\Distributor;
use App\Models\PointRedemption;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointRedemption>
 */
final class PointRedemptionFactory extends Factory
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
            'points' => 100.00,
            'point_value_snapshot' => 2.00,
            'amount_mxn' => 200.00,
            'status' => PointRedemptionStatus::PENDIENTE,
        ];
    }
}