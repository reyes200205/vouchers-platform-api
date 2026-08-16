<?php

declare(strict_types=1);

namespace App\Services\Credit;

use App\Enums\CreditIncreaseRequestStatus;
use App\Models\CreditIncreaseRequest;
use App\Models\Distributor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RequestCreditIncreaseService
{
    /**
     * @param  array{requested_amount: float, reason?: string|null}  $data
     */
    public function execute(User $user, Distributor $distributor, array $data): CreditIncreaseRequest
    {
        return DB::transaction(static function () use ($user, $distributor, $data): CreditIncreaseRequest {
            if ($distributor->status === 'CERRADA' || $distributor->status === 'BLOQUEADA') {
                abort(422, 'La distribuidora no puede solicitar un aumento de línea en su estado actual.');
            }

            $pending = CreditIncreaseRequest::query()
                ->where('distributor_id', $distributor->id)
                ->whereIn('status', [
                    CreditIncreaseRequestStatus::PENDIENTE->value,
                    CreditIncreaseRequestStatus::PRE_AUTORIZADO->value,
                ])
                ->exists();

            if ($pending) {
                abort(422, 'Ya existe una solicitud de aumento de línea pendiente para esta distribuidora.');
            }

            if ((float) $data['requested_amount'] <= 0) {
                abort(422, 'El monto solicitado debe ser mayor a cero.');
            }

            return CreditIncreaseRequest::query()->create([
                'distributor_id' => $distributor->id,
                'branch_id' => $distributor->branch_id,
                'requested_by_user_id' => $user->id,
                'requested_amount' => $data['requested_amount'],
                'reason' => $data['reason'] ?? null,
                'status' => CreditIncreaseRequestStatus::PENDIENTE,
            ]);
        });
    }
}