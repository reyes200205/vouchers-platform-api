<?php

declare(strict_types=1);

namespace App\Http\Requests\Categories;

use App\Models\DistributorCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'branch_id' => ['sometimes', 'integer', 'exists:branches,id', function (string $attribute, mixed $value, \Closure $fail): void {
                $category = $this->route('distributorCategory');

                if (! $category instanceof DistributorCategory || (int) $value === $category->branch_id) {
                    return;
                }

                $newName = $this->input('name', $category->name);
                $newCode = $this->input('code', $category->code);

                $duplicateAtTarget = DistributorCategory::query()
                    ->where('branch_id', $value)
                    ->where(fn ($query) => $query
                        ->where('name', $newName)
                        ->orWhere('code', $newCode))
                    ->whereKeyNot($category->id)
                    ->exists();

                if ($duplicateAtTarget) {
                    $fail('La categoría ya existe en la sucursal destino con el mismo código o nombre.');
                }
            }],
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('distributor_categories', 'code')
                ->where(fn ($query) => $query->where('branch_id', $this->input('branch_id', $this->route('distributorCategory')?->branch_id)))
                ->ignore($this->route('distributorCategory')?->id)],
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('distributor_categories', 'name')
                ->where(fn ($query) => $query->where('branch_id', $this->input('branch_id', $this->route('distributorCategory')?->branch_id)))
                ->ignore($this->route('distributorCategory')?->id)],
            'commission_percentage' => ['sometimes', 'decimal:0,4', 'between:0,100'],
            'points_per_1200' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'late_penalty_percentage' => ['sometimes', 'nullable', 'decimal:0,4', 'between:0,100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}