<?php

declare(strict_types=1);

namespace App\Http\Requests\Branches;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string $name
 * @property string $address
 * @property string $phone
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
            'code' => ['nullable', 'string', 'max:30', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            // Opcional: una sucursal se puede crear vacia (sin gerente) y el
            // personal -- incluido su gerente -- se asigna despues, desde
            // Staff o editando la sucursal.
            'manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
