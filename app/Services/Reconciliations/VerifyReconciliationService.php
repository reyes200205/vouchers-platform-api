<?php

declare(strict_types=1);

namespace App\Services\Reconciliations;

use App\Enums\CutoffRelationStatus;
use App\Enums\DistributorPaymentStatus;
use App\Enums\ReconciliationStatus;
use App\Models\Reconciliation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class VerifyReconciliationService
{
    public function execute(User $user, Reconciliation $reconciliation): Reconciliation
    {
        if ($reconciliation->status !== ReconciliationStatus::PENDIENTE_VERIFICACION) {
            abort(422, 'La conciliación no está pendiente de verificación.');
        }

        if ($reconciliation->reconciled_by_user_id === $user->id) {
            abort(422, 'La segunda autorización debe realizarla un usuario distinto al que registró la conciliación.');
        }

        return DB::transaction(function () use ($user, $reconciliation): Reconciliation {
            $payment = $reconciliation->distributorPayment;
            $relation = $payment->cutoffRelation;

            $payment->update(['status' => DistributorPaymentStatus::RECONCILED]);

            $reconciliation->update([
                'status' => abs((float) $reconciliation->amount_difference) <= 0.01
                    ? ReconciliationStatus::CONCILIADA
                    : ReconciliationStatus::CON_DIFERENCIA,
                'verified_by_user_id' => $user->id,
                'verified_at' => now(),
            ]);

            $relation->update([
                'status' => abs((float) $reconciliation->amount_difference) <= 0.01
                    ? CutoffRelationStatus::PAGADA
                    : CutoffRelationStatus::PARCIAL,
            ]);

            $relation->distributor()->increment('available_credit', (float) $payment->amount);

            return $reconciliation->refresh();
        });
    }
}