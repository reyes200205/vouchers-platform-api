<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Reconciliation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reconciliation
 */
final class ReconciliationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'distributor_payment_id' => $this->distributor_payment_id,
            'bank_transaction_id' => $this->bank_transaction_id,
            'original_cutoff_relation_id' => $this->original_cutoff_relation_id,
            'reconciled_by_user_id' => $this->reconciled_by_user_id,
            'verified_by_user_id' => $this->verified_by_user_id,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'reconciled_at' => $this->reconciled_at?->toIso8601String(),
            'reconciled_amount' => $this->reconciled_amount,
            'amount_difference' => $this->amount_difference,
            'status' => $this->status?->value,
            'is_retroactive_correction' => (bool) $this->is_retroactive_correction,
            'waived_late_fees_total' => $this->waived_late_fees_total,
            'notes' => $this->notes,
            // El gerente que decide (VerifyReconciliationModal/DecideReconciliationModal
            // en el frontend) solo veía ids sueltos (cutoff_relation_id,
            // distributor_id) sin nombre de la distribuidora ni datos de la
            // relación (estado, periodo, sucursal, cuánto debía) -- tenía que
            // adivinar o ir a buscarlos a otra pantalla antes de aprobar o
            // rechazar. Se agrega aquí toda esa información de solo lectura.
            'distributor_payment' => $this->whenLoaded('distributorPayment', fn () => [
                'id' => $this->distributorPayment->id,
                'cutoff_relation_id' => $this->distributorPayment->cutoff_relation_id,
                'distributor_id' => $this->distributorPayment->distributor_id,
                'amount' => $this->distributorPayment->amount,
                'reported_reference' => $this->distributorPayment->reported_reference,
                'status' => $this->distributorPayment->status?->value,
                'distributor' => $this->distributorPayment->relationLoaded('distributor') && $this->distributorPayment->distributor
                    ? [
                        'id' => $this->distributorPayment->distributor->id,
                        'distributor_number' => $this->distributorPayment->distributor->distributor_number,
                        'name' => $this->distributorPayment->distributor->relationLoaded('person') && $this->distributorPayment->distributor->person
                            ? trim(($this->distributorPayment->distributor->person->first_name ?? '').' '.($this->distributorPayment->distributor->person->last_name ?? ''))
                            : null,
                        'category' => $this->distributorPayment->distributor->relationLoaded('category') && $this->distributorPayment->distributor->category
                            ? [
                                'code' => $this->distributorPayment->distributor->category->code,
                                'name' => $this->distributorPayment->distributor->category->name,
                            ]
                            : null,
                    ]
                    : null,
                'cutoff_relation' => $this->distributorPayment->relationLoaded('cutoffRelation') && $this->distributorPayment->cutoffRelation
                    ? [
                        'id' => $this->distributorPayment->cutoffRelation->id,
                        'relation_number' => $this->distributorPayment->cutoffRelation->relation_number,
                        'status' => $this->distributorPayment->cutoffRelation->status?->value,
                        'total_payment' => $this->distributorPayment->cutoffRelation->total_payment,
                        'total_commission' => $this->distributorPayment->cutoffRelation->total_commission,
                        'total_late_fees' => $this->distributorPayment->cutoffRelation->total_late_fees,
                        'total_amount_due' => $this->distributorPayment->cutoffRelation->total_amount_due,
                        'payment_due_date' => $this->distributorPayment->cutoffRelation->payment_due_date?->toDateString(),
                        'early_payment_start_date' => $this->distributorPayment->cutoffRelation->early_payment_start_date?->toDateString(),
                        'early_payment_end_date' => $this->distributorPayment->cutoffRelation->early_payment_end_date?->toDateString(),
                        'cutoff' => $this->distributorPayment->cutoffRelation->relationLoaded('cutoff') && $this->distributorPayment->cutoffRelation->cutoff
                            ? [
                                'id' => $this->distributorPayment->cutoffRelation->cutoff->id,
                                'branch_id' => $this->distributorPayment->cutoffRelation->cutoff->branch_id,
                                'branch_name' => $this->distributorPayment->cutoffRelation->cutoff->relationLoaded('branch') && $this->distributorPayment->cutoffRelation->cutoff->branch
                                    ? $this->distributorPayment->cutoffRelation->cutoff->branch->name
                                    : null,
                                'period_start' => $this->distributorPayment->cutoffRelation->cutoff->period_start?->toDateString(),
                                'scheduled_at' => $this->distributorPayment->cutoffRelation->cutoff->scheduled_at?->toIso8601String(),
                            ]
                            : null,
                    ]
                    : null,
            ]),
            'bank_transaction' => $this->whenLoaded('bankTransaction', fn () => $this->bankTransaction ? [
                'id' => $this->bankTransaction->id,
                'reference' => $this->bankTransaction->reference,
                'transaction_date' => $this->bankTransaction->transaction_date?->toDateString(),
                'amount' => $this->bankTransaction->amount,
                'raw_description' => $this->bankTransaction->raw_description,
            ] : null),
        ];
    }
}