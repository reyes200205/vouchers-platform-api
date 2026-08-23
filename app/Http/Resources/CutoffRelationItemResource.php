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
            // Solo informativo: cuánta comisión hubiera ganado la
            // distribuidora sobre este vale si hubiera pagado a tiempo
            // (según su categoría/producto -- ver
            // GenerateCutoffService::calculateDistributorCommission). No
            // cambia commission_amount (0 real, la distribuidora sí la
            // pierde por el atraso -- ver MarkOverdueRelationsService); esto
            // solo se muestra en el detalle para que quede claro cuánto se
            // perdió, no para volver a sumarlo a ningún total.
            'commission_forfeited_amount' => $this->when(
                $this->is_late_payment && $this->relationLoaded('voucher') && $this->voucher !== null && $this->voucher->total_fortnights > 0,
                fn () => round((float) $this->voucher->distributor_profit_amount / $this->voucher->total_fortnights, 2)
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