<?php

declare(strict_types=1);

namespace App\Http\Requests\FinancialProducts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreFinancialProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'unique:financial_products,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'principal_amount' => ['required', 'decimal:0,2', 'min:0'],
            'number_of_fortnights' => ['required', 'integer', 'min:1'],
            'company_commission_percentage' => ['required', 'decimal:0,4', 'between:0,100'],
            'insurance_amount' => ['required', 'decimal:0,2', 'min:0'],
            'fortnightly_interest_percentage' => ['required', 'decimal:0,4', 'between:0,100'],
            'late_fee_amount' => ['required', 'decimal:0,2', 'min:0'],
            'disbursement_method' => ['required', 'in:TRANSFERENCIA,EFECTIVO,MIXTO'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
