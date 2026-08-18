<?php

declare(strict_types=1);

namespace App\Http\Requests\Categories;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDistributorCategoryRequest extends FormRequest
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
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'code' => ['required', 'string', 'max:30', Rule::unique('distributor_categories', 'code')->where(fn ($query) => $query->where('branch_id', $this->input('branch_id')))],
            'name' => ['required', 'string', 'max:100', Rule::unique('distributor_categories', 'name')->where(fn ($query) => $query->where('branch_id', $this->input('branch_id')))],
            'commission_percentage' => ['required', 'decimal:0,4', 'between:0,100'],
            'points_per_1200' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'late_penalty_percentage' => ['sometimes', 'nullable', 'decimal:0,4', 'between:0,100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}