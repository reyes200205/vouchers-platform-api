<?php

declare(strict_types=1);

namespace App\Http\Requests\FinancialProducts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateFinancialProductRequest extends FormRequest
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
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('financial_products', 'code')->ignore($this->route('financialProduct'))],
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', Rule::exists('distributor_categories', 'id')],
            'principal_amount' => ['sometimes', 'decimal:0,2', 'min:0'],
            'number_of_fortnights' => ['sometimes', 'integer', 'min:1'],
            'company_commission_percentage' => ['sometimes', 'decimal:0,4', 'between:0,100'],
            'insurance_amount' => ['sometimes', 'decimal:0,2', 'min:0'],
            'fortnightly_interest_percentage' => ['sometimes', 'decimal:0,4', 'between:0,100'],
            'disbursement_method' => ['sometimes', 'in:TRANSFERENCIA,EFECTIVO,MIXTO'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
