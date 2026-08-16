<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CutoffRelationStatus;
use App\Enums\DistributorStatus;
use App\Models\CutoffRelation;
use App\Models\Distributor;
use App\Models\User;
use App\Notifications\DistributorBlockedNotification;
use App\Services\Cutoffs\MarkOverdueRelationsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

final class MarkOverdueRelationsCommand extends Command
{
    protected $signature = 'cutoffs:mark-overdue';

    protected $description = 'Marca como vencidas las relaciones sin pagar y bloquea a las distribuidoras con 3 cortes consecutivos.';

    public function handle(MarkOverdueRelationsService $service): int
    {
        $marked = $service->execute();
        $blocked = 0;

        $distributorIds = CutoffRelation::query()
            ->where('status', CutoffRelationStatus::VENCIDA)
            ->distinct()
            ->pluck('distributor_id');

        foreach ($distributorIds as $distributorId) {
            $consecutive = $this->countConsecutiveOverdue($distributorId);

            if ($consecutive < 3) {
                continue;
            }

            $distributor = Distributor::query()->find($distributorId);

            if ($distributor === null || $distributor->status === DistributorStatus::BLOQUEADA) {
                continue;
            }

            $distributor->update([
                'status' => DistributorStatus::BLOQUEADA,
                'can_issue_vouchers' => false,
            ]);

            $distributor->load('coordinator');

            $distributorUsers = User::query()
                ->where('person_id', $distributor->person_id)
                ->get();

            Notification::send(
                array_filter([
                    $distributor->coordinator,
                    ...$distributorUsers->all(),
                ]),
                new DistributorBlockedNotification($distributor, $consecutive)
            );

            $blocked++;
        }

        $this->info("Relaciones vencidas: {$marked}, distribuidoras bloqueadas: {$blocked}");

        return self::SUCCESS;
    }

    private function countConsecutiveOverdue(int $distributorId): int
    {
        $relations = CutoffRelation::query()
            ->where('distributor_id', $distributorId)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'status']);

        $count = 0;

        foreach ($relations as $relation) {
            if (!in_array($relation->status->value, ['VENCIDA', 'CERRADA'], true)) {
                break;
            }

            $count++;
        }

        return $count;
    }
}