<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CutoffStatus;
use App\Enums\CutoffType;
use App\Models\Branch;
use App\Models\Cutoff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cutoff>
 */
final class CutoffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'cutoff_type' => CutoffType::PAGOS,
            'base_day_of_month' => now()->day,
            'base_time' => now()->format('H:i:s'),
            'scheduled_at' => now(),
            'keep_date_on_holiday' => true,
            'status' => CutoffStatus::EJECUTADO,
        ];
    }
}