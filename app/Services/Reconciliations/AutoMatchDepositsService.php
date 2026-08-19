<?php

declare(strict_types=1);

namespace App\Services\Reconciliations;

use App\Enums\CutoffRelationStatus;
use App\Enums\DistributorPaymentStatus;
use App\Enums\ReconciliationStatus;
use App\Models\BankTransaction;
use App\Models\CutoffRelation;
use App\Models\DistributorPayment;
use App\Models\Reconciliation;
use App\Models\User;
use App\Services\Cutoffs\SettleCutoffRelationService;
use Illuminate\Support\Facades\DB;

final class AutoMatchDepositsService
{
    public function __construct(
        private readonly SettleCutoffRelationService $settleCutoffRelationService,
    ) {
    }

    /**
     * Matches imported bank transactions against open cutoff relations by payment reference.
     *
     * @return int number of transactions matched
     */
    public function execute(User $user): int
    {
        $transactions = BankTransaction::query()
            ->whereNotNull('reference')
            ->whereDoesntHave('reconciliation')
            ->orderBy('transaction_date')
            ->get();

        $matched = 0;

        foreach ($transactions as $transaction) {
            $relation = $this->findRelation($transaction);

            if ($relation === null) {
                continue;
            }

            DB::transaction(function () use ($user, $transaction, $relation): void {
                $payment = DistributorPayment::query()->create([
                    'cutoff_relation_id' => $relation->id,
                    'distributor_id' => $relation->distributor_id,
                    'amount' => $transaction->amount,
                    'payment_method' => 'TRANSFER',
                    'reported_reference' => $transaction->reference,
                    'payment_date' => $transaction->transaction_date->toDateTimeString(),
                    'status' => DistributorPaymentStatus::DETECTED,
                ]);

                $difference = round((float) $transaction->amount - (float) $relation->total_amount_due, 2);
                $status = abs($difference) <= 0.01 ? ReconciliationStatus::CONCILIADA : ReconciliationStatus::CON_DIFERENCIA;

                $reconciliation = Reconciliation::query()->create([
                    'distributor_payment_id' => $payment->id,
                    'bank_transaction_id' => $transaction->id,
                    'reconciled_by_user_id' => $user->id,
                    'reconciled_at' => now(),
                    'reconciled_amount' => $transaction->amount,
                    'amount_difference' => $difference,
                    'status' => $status,
                    'notes' => 'Conciliación automática por referencia.',
                ]);

                $this->applyPayment($relation, $payment, $reconciliation);
            });

            $matched++;
        }

        return $matched;
    }

    private function findRelation(BankTransaction $transaction): ?CutoffRelation
    {
        $reference = strtoupper(preg_replace('/\s+/', '', (string) $transaction->reference));

        if ($reference === '') {
            return null;
        }

        $relation = CutoffRelation::query()
            ->whereIn('status', [CutoffRelationStatus::GENERADA, CutoffRelationStatus::PARCIAL, CutoffRelationStatus::VENCIDA])
            ->get()
            ->first(fn (CutoffRelation $candidate) => strtoupper(preg_replace('/\s+/', '', (string) $candidate->payment_reference)) === $reference);

        if ($relation === null) {
            return null;
        }

        if (abs((float) $transaction->amount - (float) $relation->total_amount_due) <= 0.01) {
            return $relation;
        }

        if ($relation->status === CutoffRelationStatus::PARCIAL) {
            $paid = $relation->payments()->sum('amount');

            if (abs((float) $paid - (float) $relation->total_amount_due) <= 0.01) {
                return null;
            }
        }

        return $relation;
    }

    private function applyPayment(CutoffRelation $relation, DistributorPayment $payment, Reconciliation $reconciliation): void
    {
        if ($reconciliation->status === ReconciliationStatus::CONCILIADA) {
            $payment->update(['status' => DistributorPaymentStatus::RECONCILED]);
            $relation->update(['status' => CutoffRelationStatus::PAGADA]);
        } else {
            $payment->update(['status' => DistributorPaymentStatus::RECONCILED]);
            $relation->update(['status' => CutoffRelationStatus::PARCIAL]);
        }

        $relation->distributor()->increment('available_credit', (float) $payment->amount);

        // Solo hace algo si la relación quedó PAGADA: avanza los vales detrás de
        // ella y le otorga los puntos a la distribuidora (nunca al cliente).
        $this->settleCutoffRelationService->execute($relation->refresh());
    }
}