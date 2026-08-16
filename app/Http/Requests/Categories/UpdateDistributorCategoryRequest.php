<?php

declare(strict_types=1);

namespace App\Http\Requests\Categories;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateDistributorCategoryRequest extends FormRequest
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
            'code' => ['sometimes', 'string', 'max:30', 'unique:distributor_categories,code,'.$this->route('distributorCategory')?->id],
            'name' => ['sometimes', 'string', 'max:100', 'unique:distributor_categories,name,'.$this->route('distributorCategory')?->id],
            'commission_percentage' => ['sometimes', 'decimal:0,4', 'between:0,100'],
            'points_per_1200' => ['sometimes', 'integer', 'min:0'],
            'late_penalty_percentage' => ['sometimes', 'decimal:0,4', 'between:0,100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}