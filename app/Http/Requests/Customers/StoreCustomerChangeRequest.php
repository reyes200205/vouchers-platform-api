<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use App\Enums\ChangeType;
use App\Rules\ValidCurp;
use App\Rules\ValidRfc;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreCustomerChangeRequest extends FormRequest
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
        $personId = $this->route('customer')?->person?->id;

        return [
            'change_type' => ['required', new Enum(ChangeType::class)],
            'new_values' => ['required', 'array'],
            'new_values.curp' => ['sometimes', 'nullable', 'string', 'size:18', new ValidCurp(), Rule::unique('people', 'curp')->ignore($personId)],
            'new_values.rfc' => ['sometimes', 'nullable', 'string', 'max:13', new ValidRfc(), Rule::unique('people', 'rfc')->ignore($personId)],
            'evidence' => ['nullable', 'array'],
            'evidence.*' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
