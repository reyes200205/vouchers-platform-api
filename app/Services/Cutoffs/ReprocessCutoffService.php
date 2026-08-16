<?php

declare(strict_types=1);

namespace App\Services\Cutoffs;

use App\Enums\CutoffRelationStatus;
use App\Enums\CutoffStatus;
use App\Models\Cutoff;
use App\Models\CutoffRelation;
use Illuminate\Support\Facades\DB;

final class ReprocessCutoffService
{
    /**
     * Regenerates the relations of an already executed cutoff, closing the previous ones.
     */
    public function execute(Cutoff $cutoff): Cutoff
    {
        if ($cutoff->status === CutoffStatus::PROGRAMADO) {
            abort(422, 'El corte aún no ha sido ejecutado.');
        }

        return DB::transaction(function () use ($cutoff): Cutoff {
            $cutoff->update(['status' => CutoffStatus::REPROCESADO]);

            $newCutoff = Cutoff::query()->create([
                'branch_id' => $cutoff->branch_id,
                'cutoff_type' => $cutoff->cutoff_type,
                'base_day_of_month' => $cutoff->base_day_of_month,
                'base_time' => $cutoff->base_time,
                'scheduled_at' => $cutoff->scheduled_at,
                'executed_at' => now(),
                'status' => CutoffStatus::EJECUTADO,
                'config_snapshot_json' => $cutoff->config_snapshot_json,
                'notes' => 'Reproceso del corte #' . $cutoff->id,
            ]);

            $distributorIds = CutoffRelation::query()
                ->where('cutoff_id', $cutoff->id)
                ->distinct()
                ->pluck('distributor_id');

            foreach ($distributorIds as $distributorId) {
                CutoffRelation::query()->create([
                    'cutoff_id' => $newCutoff->id,
                    'distributor_id' => $distributorId,
                    'previous_relation_id' => $cutoff->id,
                    'relation_number' => 'REL-' . $newCutoff->id . '-' . $distributorId,
                    'payment_reference' => 'REF-' . strtoupper(substr(md5(uniqid((string) $distributorId, true)), 0, 10)),
                    'payment_due_date' => $cutoff->relations()->first()?->payment_due_date ?? now()->addDays(15)->toDateString(),
                    'status' => CutoffRelationStatus::GENERADA,
                    'generated_at' => now(),
                ]);
            }

            return $newCutoff->refresh();
        });
    }
}