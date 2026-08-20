<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomerRequest extends FormRequest
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
            'person' => ['required', 'array'],
            'person.first_name' => ['required', 'string', 'max:100'],
            'person.middle_name' => ['nullable', 'string', 'max:100'],
            'person.last_name' => ['required', 'string', 'max:100'],
            'person.second_last_name' => ['nullable', 'string', 'max:100'],
            'person.gender' => ['nullable', 'in:M,F,OTHER'],
            'person.birth_date' => ['nullable', 'date'],
            'person.curp' => ['required', 'string', 'size:18', 'unique:people,curp'],
            'person.rfc' => ['nullable', 'string', 'max:13', 'unique:people,rfc'],
            'person.home_phone' => ['nullable', 'string', 'max:20'],
            'person.mobile_phone' => ['nullable', 'string', 'max:20'],
            'person.email' => ['nullable', 'email', 'max:150'],
            'person.street' => ['nullable', 'string', 'max:150'],
            'person.external_number' => ['nullable', 'string', 'max:30'],
            'person.neighborhood' => ['nullable', 'string', 'max:120'],
            'person.city' => ['nullable', 'string', 'max:120'],
            'person.state' => ['nullable', 'string', 'max:120'],
            'person.postal_code' => ['nullable', 'string', 'max:10'],
            'person.latitude' => ['nullable', 'decimal:0,7'],
            'person.longitude' => ['nullable', 'decimal:0,7'],
            'person.notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
