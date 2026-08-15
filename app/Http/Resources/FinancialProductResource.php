<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FinancialProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinancialProduct
 */
final class FinancialProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'principal_amount' => $this->principal_amount,
            'number_of_fortnights' => $this->number_of_fortnights,
            'company_commission_percentage' => $this->company_commission_percentage,
            'insurance_amount' => $this->insurance_amount,
            'fortnightly_interest_percentage' => $this->fortnightly_interest_percentage,
            'late_fee_amount' => $this->late_fee_amount,
            'disbursement_method' => $this->disbursement_method?->value,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
