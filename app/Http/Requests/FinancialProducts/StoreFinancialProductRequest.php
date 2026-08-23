<?php

declare(strict_types=1);

namespace App\Http\Requests\FinancialProducts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'code' => ['sometimes', 'string', 'max:30', 'unique:financial_products,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', Rule::exists('distributor_categories', 'id')],
            'principal_amount' => ['required', 'decimal:0,2', 'min:0'],
            'number_of_fortnights' => ['required', 'integer', 'min:1'],
            'company_commission_percentage' => ['nullable', 'decimal:0,4', 'between:0,100'],
            'insurance_amount' => ['nullable', 'decimal:0,2', 'min:0'],
            'fortnightly_interest_percentage' => ['nullable', 'decimal:0,4', 'between:0,100'],
            'late_fee_amount' => ['nullable', 'decimal:0,2', 'min:0'],
            'disbursement_method' => ['nullable', 'in:TRANSFERENCIA,EFECTIVO,MIXTO'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
