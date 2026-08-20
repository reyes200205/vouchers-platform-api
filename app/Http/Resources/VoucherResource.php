<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Voucher
 */
final class VoucherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'voucher_number' => $this->voucher_number,
            'distributor_id' => $this->distributor_id,
            'customer_id' => $this->customer_id,
            'financial_product_id' => $this->financial_product_id,
            'voucher_request_id' => $this->voucher_request_id,
            'branch_id' => $this->branch_id,
            'status' => $this->status?->value,
            'is_pre_vale' => $this->is_pre_vale,
            'amount' => $this->amount,
            'company_commission_percentage_snapshot' => $this->company_commission_percentage_snapshot,
            'company_commission_amount' => $this->company_commission_amount,
            'insurance_amount_snapshot' => $this->insurance_amount_snapshot,
            'interest_percentage_snapshot' => $this->interest_percentage_snapshot,
            'interest_amount' => $this->interest_amount,
            'distributor_profit_percentage_snapshot' => $this->distributor_profit_percentage_snapshot,
            'distributor_profit_amount' => $this->distributor_profit_amount,
            'late_fee_amount_snapshot' => $this->late_fee_amount_snapshot,
            'total_debt_amount' => $this->total_debt_amount,
            'fortnightly_payment_amount' => $this->fortnightly_payment_amount,
            'total_fortnights' => $this->total_fortnights,
            'payments_made' => $this->payments_made,
            'current_balance' => $this->current_balance,
            'transfer_reference' => $this->transfer_reference,
            'authorized_number' => $this->authorized_number,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'transferred_at' => $this->transferred_at?->toIso8601String(),
            'payment_due_date' => $this->payment_due_date?->toDateString(),
            'is_canceled' => $this->is_canceled,
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'notes' => $this->notes,
            'created_by_user_id' => $this->created_by_user_id,
            'approved_by_user_id' => $this->approved_by_user_id,
            'disbursed_by_user_id' => $this->disbursed_by_user_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'customer_code' => $this->customer->customer_code,
                'status' => $this->customer->status?->value,
                'verified_at' => $this->customer->verified_at?->toIso8601String(),
                'person' => new PersonResource($this->customer->person),
            ]),
            'distributor' => $this->whenLoaded('distributor', fn () => $this->distributor ? [
                'id' => $this->distributor->id,
                'distributor_number' => $this->distributor->distributor_number,
                'person' => $this->distributor->relationLoaded('person') && $this->distributor->person
                    ? new PersonResource($this->distributor->person)
                    : null,
            ] : null),
        ];
    }
}