<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CutoffRelationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CutoffRelationItem
 */
final class CutoffRelationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cutoff_relation_id' => $this->cutoff_relation_id,
            'voucher_id' => $this->voucher_id,
            'customer_id' => $this->customer_id,
            'product_name_snapshot' => $this->product_name_snapshot,
            'payments_made' => $this->payments_made,
            'total_payments' => $this->total_payments,
            'is_late_payment' => $this->is_late_payment,
            'installment_number' => $this->installment_number,
            'accumulated_late_installments' => $this->accumulated_late_installments,
            'commission_amount' => $this->commission_amount,
            'payment_amount' => $this->payment_amount,
            'late_fee_amount' => $this->late_fee_amount,
            'line_total_amount' => $this->line_total_amount,
            'previous_paid_amount' => $this->previous_paid_amount,
            'origin_cutoff_id' => $this->origin_cutoff_id,
            'origin_relation_id' => $this->origin_relation_id,
            // Cuanta comision perdio la distribuidora por el atraso de este
            // item (ver MarkOverdueRelationsService). Ya no se recalcula al
            // vuelo aqui -- es un valor guardado que, a diferencia de
            // commission_amount (siempre 0 en un item atrasado), SI se va
            // acumulando cada corte que la quincena FINAL del vale se
            // vuelve a vencer sin pagarse (junto con late_fee_amount), y
            // tambien esta incluido en line_total_amount desde esa segunda
            // vez en adelante.
            'commission_forfeited_amount' => $this->when(
                $this->is_late_payment,
                fn () => (float) $this->commission_forfeited_amount
            ),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'customer_code' => $this->customer->customer_code,
                'person' => $this->customer->relationLoaded('person') && $this->customer->person
                    ? new PersonResource($this->customer->person)
                    : null,
            ] : null),
        ];
    }
}