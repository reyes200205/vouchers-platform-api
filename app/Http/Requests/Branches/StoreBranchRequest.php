<?php

declare(strict_types=1);

namespace App\Http\Requests\Branches;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string $name
 * @property string|null $branch_code
 * @property string $branch_type
 * @property int|null $manager_id
 * @property string $country
 * @property string $state
 * @property string $city
 * @property string $address
 * @property string $postal_code
 */
final class StoreBranchRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'branch_code' => ['nullable', 'string', 'max:255'],
            'branch_type' => ['required', 'string', 'in:main_office,subsidiary_office'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'country' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
        ];
    }
}
