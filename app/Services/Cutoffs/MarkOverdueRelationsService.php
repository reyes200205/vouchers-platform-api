<?php

declare(strict_types=1);

namespace App\Services\Cutoffs;

use App\Enums\CutoffRelationStatus;
use App\Models\CutoffRelation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class MarkOverdueRelationsService
{
    /**
     * Marks every unpaid relation whose due date has passed as VENCIDA.
     *
     * @return int number of relations marked
     */
    public function execute(?Carbon $asOf = null): int
    {
        $asOf ??= now();

        return DB::transaction(function () use ($asOf): int {
            return CutoffRelation::query()
                ->where('status', CutoffRelationStatus::GENERADA)
                ->whereDate('payment_due_date', '<', $asOf->toDateString())
                ->update(['status' => CutoffRelationStatus::VENCIDA]);
        });
    }
}