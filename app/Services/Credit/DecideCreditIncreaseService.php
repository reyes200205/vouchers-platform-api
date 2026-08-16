<?php

declare(strict_types=1);

namespace App\Services\Credit;

use App\Enums\CreditIncreaseRequestStatus;
use App\Models\CreditIncreaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DecideCreditIncreaseService
{
    /**
     * @param  array{decision: string, approved_amount?: float|null, decision_notes?: string|null}  $data
     */
    public function execute(User $user, CreditIncreaseRequest $request, array $data): CreditIncreaseRequest
    {
        return DB::transaction(static function () use ($user, $request, $data): CreditIncreaseRequest {
            if ($request->status === CreditIncreaseRequestStatus::PENDIENTE) {
                abort(422, 'La solicitud aún no ha sido pre-autorizada por el coordinador.');
            }

            if ($request->status !== CreditIncreaseRequestStatus::PRE_AUTORIZADO) {
                abort(422, 'La solicitud ya fue resuelta.');
            }

            $request->load('distributor');
            $decision = $data['decision'];
            $approvedAmount = null;

            if ($decision === 'APROBADO' || $decision === 'REDUCIDO') {
                $approvedAmount = (float) ($data['approved_amount'] ?? $request->pre_authorized_amount);

                if ($approvedAmount <= 0) {
                    abort(422, 'El monto aprobado debe ser mayor a cero.');
                }

                if ($approvedAmount > (float) $request->pre_authorized_amount) {
                    abort(422, 'El monto aprobado no puede superar el monto pre-autorizado.');
                }

                $request->distributor->increment('credit_limit', $approvedAmount);
                $request->distributor->increment('available_credit', $approvedAmount);
                $request->distributor->update(['prevale_required_after_credit_increase_at' => now()]);
            }

            $request->update([
                'status' => $decision,
                'approved_amount' => $approvedAmount,
                'decided_by_user_id' => $user->id,
                'decision_notes' => $data['decision_notes'] ?? $request->decision_notes,
                'decided_at' => now(),
            ]);

            return $request;
        });
    }
}