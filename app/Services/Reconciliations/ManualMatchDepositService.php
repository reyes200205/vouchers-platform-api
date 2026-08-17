<?php

declare(strict_types=1);

namespace App\Services\Reconciliations;

use App\Enums\DistributorPaymentStatus;
use App\Enums\ReconciliationStatus;
use App\Models\BankTransaction;
use App\Models\CutoffRelation;
use App\Models\DistributorPayment;
use App\Models\Reconciliation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ManualMatchDepositService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, BankTransaction $transaction, array $data): Reconciliation
    {
        if ($transaction->reconciliation()->exists()) {
            abort(422, 'La transacción bancaria ya fue conciliada.');
        }

        $relation = CutoffRelation::query()->findOrFail($data['cutoff_relation_id']);

        if (!in_array($relation->status->value, ['GENERADA', 'PARCIAL', 'VENCIDA'], true)) {
            abort(422, 'La relación seleccionada ya no admite pagos.');
        }

        return DB::transaction(function () use ($user, $transaction, $relation, $data): Reconciliation {
            $payment = DistributorPayment::query()->create([
                'cutoff_relation_id' => $relation->id,
                'distributor_id' => $relation->distributor_id,
                'amount' => (float) ($data['amount'] ?? $transaction->amount),
                'payment_method' => $data['payment_method'] ?? 'DEPOSIT',
                'reported_reference' => $transaction->reference,
                'payment_date' => $transaction->transaction_date->toDateTimeString(),
                'status' => DistributorPaymentStatus::DETECTED,
                'notes' => $data['notes'] ?? null,
            ]);

            $difference = round((float) $payment->amount - (float) $relation->total_amount_due, 2);

            return Reconciliation::query()->create([
                'distributor_payment_id' => $payment->id,
                'bank_transaction_id' => $transaction->id,
                'reconciled_by_user_id' => $user->id,
                'reconciled_at' => now(),
                'reconciled_amount' => $payment->amount,
                'amount_difference' => $difference,
                'status' => ReconciliationStatus::PENDIENTE_VERIFICACION,
                'notes' => $data['notes'] ?? 'Conciliación manual. Pendiente de segunda autorización.',
            ]);
        });
    }
}