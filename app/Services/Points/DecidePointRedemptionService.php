<?php

declare(strict_types=1);

namespace App\Services\Points;

use App\Enums\PointMovementType;
use App\Enums\PointRedemptionStatus;
use App\Models\PointMovement;
use App\Models\PointRedemption;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DecidePointRedemptionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, PointRedemption $redemption, array $data): PointRedemption
    {
        if ($redemption->status !== PointRedemptionStatus::PENDIENTE) {
            abort(422, 'El canje ya fue resuelto.');
        }

        $decision = $data['decision'];

        return DB::transaction(function () use ($user, $redemption, $decision, $data): PointRedemption {
            if ($decision === 'APROBADO') {
                $distributor = $redemption->distributor;

                if ((float) $distributor->current_points < (float) $redemption->points) {
                    abort(422, 'La distribuidora ya no tiene suficientes puntos.');
                }

                $distributor->decrement('current_points', (float) $redemption->points);

                PointMovement::query()->create([
                    'distributor_id' => $redemption->distributor_id,
                    'transaction_type' => PointMovementType::CANJE,
                    'points' => -(float) $redemption->points,
                    'point_value_snapshot' => $redemption->point_value_snapshot,
                    'reason' => 'Canje de puntos aprobado por ' . ($user->username ?? 'gerente') . '.',
                    'transaction_date' => now(),
                ]);
            }

            $redemption->update([
                'status' => $decision === 'APROBADO' ? PointRedemptionStatus::APROBADO : PointRedemptionStatus::RECHAZADO,
                'decided_by_user_id' => $user->id,
                'decision_notes' => $data['decision_notes'] ?? $redemption->decision_notes,
                'decided_at' => now(),
            ]);

            return $redemption->refresh();
        });
    }
}