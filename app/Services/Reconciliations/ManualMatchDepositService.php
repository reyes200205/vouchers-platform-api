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

        // Una relación PAGADA normalmente ya no admite pagos -- pero si se
        // liquidó CON multa (total_late_fees > 0), puede que en realidad el
        // pago sí haya llegado a tiempo y la multa se haya aplicado por error
        // de la cajera (nunca registró el depósito real). Se deja seleccionar
        // para poder corregirla (ver RetroactiveReconciliationService); una
        // PAGADA sin multa no tiene nada que corregir y se sigue rechazando.
        $isPagadaConMultaPorCorregir = $relation->status === CutoffRelationStatus::PAGADA
            && (float) $relation->total_late_fees > 0;

        $isReachableNormalmente = in_array($relation->status->value, ['GENERADA', 'PARCIAL', 'VENCIDA', 'CERRADA'], true);

        if (! $isReachableNormalmente && ! $isPagadaConMultaPorCorregir) {
            abort(422, 'La relación seleccionada ya no admite pagos.');
        }

        // Se marca como corrección retroactiva (y por lo tanto se resuelve con
        // RetroactiveReconciliationService al segundo autorizar, no con el
        // flujo normal) siempre que la relación elegida ya esté CERRADA
        // (arrastrada a una relación más nueva) o ya traiga multa aplicada --
        // en ambos casos hay algo que potencialmente corregir según la fecha
        // real del depósito, sin importar si al final resulta que sí llegó
        // atrasado de verdad.
        $isRetroactiveCorrection = $relation->status === CutoffRelationStatus::CERRADA
            || (float) $relation->total_late_fees > 0;

        return DB::transaction(function () use ($user, $transaction, $relation, $isRetroactiveCorrection, $data): Reconciliation {
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

            // El monto contra el que se compara aquí es solo un preview para la
            // cajera: si es una corrección retroactiva, el monto real que debía
            // la relación se recalcula sin la multa hasta que el gerente aprueba
            // (RetroactiveReconciliationService), así que esta diferencia puede
            // no coincidir con la que quede al final.
            $difference = round((float) $payment->amount - (float) $relation->total_amount_due, 2);

            return Reconciliation::query()->create([
                'distributor_payment_id' => $payment->id,
                'bank_transaction_id' => $transaction->id,
                'original_cutoff_relation_id' => $relation->id,
                'reconciled_by_user_id' => $user->id,
                'reconciled_at' => now(),
                'reconciled_amount' => $payment->amount,
                'amount_difference' => $difference,
                'status' => ReconciliationStatus::PENDIENTE_VERIFICACION,
                'is_retroactive_correction' => $isRetroactiveCorrection,
                'notes' => $data['notes'] ?? ($isRetroactiveCorrection
                    ? 'Posible corrección retroactiva (pago real no registrado a tiempo). Pendiente de segunda autorización.'
                    : 'Conciliación manual. Pendiente de segunda autorización.'),
            ]);
        });
    }
}