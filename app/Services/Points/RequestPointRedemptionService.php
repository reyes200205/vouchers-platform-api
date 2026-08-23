<?php

declare(strict_types=1);

namespace App\Services\Points;

use App\Enums\PointRedemptionStatus;
use App\Models\Distributor;
use App\Models\PointRedemption;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RequestPointRedemptionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, Distributor $distributor, array $data): PointRedemption
    {
        $points = (float) $data['points'];

        if ($points <= 0) {
            abort(422, 'Los puntos a canjear deben ser mayores a cero.');
        }

        if ($points > (float) $distributor->current_points) {
            abort(422, 'La distribuidora no tiene suficientes puntos disponibles.');
        }

        if (PointRedemption::query()->where('distributor_id', $distributor->id)->where('status', PointRedemptionStatus::PENDIENTE)->exists()) {
            abort(422, 'Ya existe un canje pendiente de aprobación.');
        }

        $valuePerPoint = (float) ($distributor->branch->branchSetting?->point_value_mxn ?? 2.00);
        $amount = round($points * $valuePerPoint, 2);

        return DB::transaction(function () use ($user, $distributor, $points, $valuePerPoint, $amount): PointRedemption {
            $redemption = PointRedemption::query()->create([
                'distributor_id' => $distributor->id,
                'branch_id' => $distributor->branch_id,
                'requested_by_user_id' => $user->id,
                'points' => $points,
                'point_value_snapshot' => $valuePerPoint,
                'amount_mxn' => $amount,
                'status' => PointRedemptionStatus::PENDIENTE,
            ]);

            $redemption->update(['folio' => 'CANJE-'.str_pad((string) $redemption->id, 8, '0', STR_PAD_LEFT)]);

            return $redemption;
        });
    }
}