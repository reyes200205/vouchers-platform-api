<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CutoffRelationStatus;
use App\Enums\DistributorStatus;
use App\Models\CutoffRelation;
use App\Models\Distributor;
use App\Models\User;
use App\Notifications\DistributorDelinquentNotification;
use App\Notifications\DistributorOverdueNoticeNotification;
use App\Services\Cutoffs\MarkOverdueRelationsService;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification as NotificationMessage;
use Illuminate\Support\Facades\Notification;

final class MarkOverdueRelationsCommand extends Command
{
    protected $signature = 'cutoffs:mark-overdue';

    protected $description = 'Marca como vencidas las relaciones sin pagar; avisa a la distribuidora al segundo corte consecutivo sin pagar y la marca MOROSA (ya no puede emitir vales) al tercero.';

    /**
     * A partir de cuántos cortes consecutivos sin pagar se manda el aviso
     * preventivo -- todavía no se le restringe nada a la distribuidora.
     */
    private const NOTICE_THRESHOLD = 2;

    /**
     * A partir de cuántos cortes consecutivos sin pagar la distribuidora
     * queda MOROSA (ya no puede emitir vales nuevos).
     */
    private const DELINQUENT_THRESHOLD = 3;

    public function handle(MarkOverdueRelationsService $service): int
    {
        $marked = $service->execute();
        $noticed = 0;
        $markedDelinquent = 0;

        $distributorIds = CutoffRelation::query()
            ->where('status', CutoffRelationStatus::VENCIDA)
            ->distinct()
            ->pluck('distributor_id');

        foreach ($distributorIds as $distributorId) {
            $consecutive = $this->countConsecutiveOverdue($distributorId);

            if ($consecutive < self::NOTICE_THRESHOLD) {
                continue;
            }

            $distributor = Distributor::query()->find($distributorId);

            if ($distributor === null) {
                continue;
            }

            if ($consecutive >= self::DELINQUENT_THRESHOLD) {
                // Ya estaba MOROSA de una corrida anterior -- no se vuelve a
                // notificar cada vez que el comando corre mientras siga sin
                // pagar (el aviso y la marca MOROSA solo pasan una vez cada
                // uno; no hay todavía un flujo de "recuperación" que la
                // regrese a ACTIVA -- eso queda fuera de este cambio).
                if ($distributor->status === DistributorStatus::MOROSA) {
                    continue;
                }

                $distributor->update([
                    'status' => DistributorStatus::MOROSA,
                    'can_issue_vouchers' => false,
                ]);

                $this->notifyDistributor($distributor, new DistributorDelinquentNotification($distributor, $consecutive));
                $markedDelinquent++;

                continue;
            }

            // Exactamente en el umbral de aviso (2): todavía no llega al de
            // MOROSA (3), solo se notifica. Nota: a diferencia de la marca
            // MOROSA (que es idempotente por sí misma, vía el status), este
            // aviso no lleva deduplicación propia -- si la distribuidora se
            // queda exactamente en 2 cortes consecutivos varias corridas
            // seguidas (sin generarse un tercer corte todavía), se le vuelve
            // a mandar cada vez que corre el comando. Iguala el criterio ya
            // usado por vouchers:send-reminders (tampoco deduplica), y evita
            // sumarle una tabla/columna nueva de seguimiento solo para esto.
            $this->notifyDistributor($distributor, new DistributorOverdueNoticeNotification($distributor, $consecutive));
            $noticed++;
        }

        $this->info("Relaciones vencidas: {$marked}, avisos enviados: {$noticed}, distribuidoras marcadas como morosas: {$markedDelinquent}");

        return self::SUCCESS;
    }

    private function notifyDistributor(Distributor $distributor, NotificationMessage $notification): void
    {
        $distributor->load('coordinator');

        $distributorUsers = User::query()
            ->where('person_id', $distributor->person_id)
            ->get();

        Notification::send(
            array_filter([
                $distributor->coordinator,
                ...$distributorUsers->all(),
            ]),
            $notification
        );
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
