<?php

declare(strict_types=1);

namespace App\Services\Credit;

use App\Enums\CreditIncreaseRequestStatus;
use App\Models\CreditIncreaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PreAuthorizeCreditIncreaseService
{
    /**
     * @param  array{pre_authorized_amount: float, decision_notes?: string|null}  $data
     */
    public function execute(User $user, CreditIncreaseRequest $request, array $data): CreditIncreaseRequest
    {
        return DB::transaction(static function () use ($user, $request, $data): CreditIncreaseRequest {
            if ($request->status !== CreditIncreaseRequestStatus::PENDIENTE) {
                abort(422, 'La solicitud ya fue resuelta.');
            }

            $preAuthorized = (float) $data['pre_authorized_amount'];
            if ($preAuthorized <= 0 || $preAuthorized > (float) $request->requested_amount) {
                abort(422, 'El monto pre-autorizado debe ser mayor a cero y no superar el monto solicitado.');
            }

            $request->update([
                'status' => CreditIncreaseRequestStatus::PRE_AUTORIZADO,
                'pre_authorized_amount' => $preAuthorized,
                'pre_authorized_by_user_id' => $user->id,
                'pre_authorized_at' => now(),
                'decision_notes' => $data['decision_notes'] ?? $request->decision_notes,
            ]);

            return $request;
        });
    }
}