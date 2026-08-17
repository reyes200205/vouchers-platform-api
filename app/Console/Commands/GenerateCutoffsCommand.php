<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\Cutoffs\GenerateCutoffService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class GenerateCutoffsCommand extends Command
{
    protected $signature = 'cutoffs:generate';

    protected $description = 'Genera los cortes de pago de todas las sucursales por su fecha base.';

    public function handle(GenerateCutoffService $service): int
    {
        $today = Carbon::today();
        $branches = Branch::query()->whereHas('cutoffs')->with('cutoffs')->get();

        if ($branches->isEmpty()) {
            $branches = Branch::query()->get();
        }

        $generated = 0;

        foreach ($branches as $branch) {
            $latest = $branch->cutoffs()->latest('scheduled_at')->first();
            $periodEnd = $latest?->scheduled_at?->addDays(15) ?? $today;

            if ($latest !== null && $periodEnd->gt($today)) {
                continue;
            }

            $periodStart = $latest?->scheduled_at?->addDay() ?? $periodEnd->copy()->subDays(15);

            $service->execute(
                (new \App\Models\User())->forceFill(['id' => 1]),
                $branch,
                [
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                ]
            );

            $generated++;
        }

        $this->info("Cortes generados: {$generated}");

        return self::SUCCESS;
    }
}