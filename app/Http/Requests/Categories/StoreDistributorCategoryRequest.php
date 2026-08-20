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
     * La sucursal viene del segmento de la URL (/branches/{branch}/categories),
     * no del cuerpo de la petición. Sin esto, `rules()` exige branch_id antes
     * de que el controlador alcance a fusionarlo (CategoryController::store lo
     * hacía después de la validación, así que siempre fallaba con "El campo
     * sucursal es obligatorio" aunque la URL ya lo trajera).
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('branch_id')) {
            $this->merge(['branch_id' => $this->route('branch')?->id]);
        }
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