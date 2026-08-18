<?php

declare(strict_types=1);

namespace App\Http\Requests\Applications;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreApplicationRequest extends FormRequest
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
            'person' => ['required', 'array'],
            'person.first_name' => ['required', 'string', 'max:100'],
            'person.middle_name' => ['nullable', 'string', 'max:100'],
            'person.last_name' => ['required', 'string', 'max:100'],
            'person.second_last_name' => ['nullable', 'string', 'max:100'],
            'person.gender' => ['nullable', 'in:M,F,OTHER'],
            'person.birth_date' => ['nullable', 'date'],
            'person.curp' => ['nullable', 'string', 'size:18', 'unique:people,curp'],
            'person.rfc' => ['nullable', 'string', 'max:13', 'unique:people,rfc'],
            'person.home_phone' => ['nullable', 'string', 'max:30'],
            'person.mobile_phone' => ['nullable', 'string', 'max:30'],
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
            // family_data agrupa: familiares/conyuge, ocupacion (donde trabaja o estudia, edad)
            // y vivienda (tenencia, dimensiones, referencia laboral). Ver new.vue en el frontend
            // para la forma exacta que arma el coordinador.
            'family_data' => ['required', 'array'],
            'family_data.applicant_age' => ['required', 'integer', 'min:18'],
            'vehicles' => ['nullable', 'array'],
            'requested_credit_limit' => ['required', 'decimal:0,2', 'min:1000'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'initial_category_code' => ['sometimes', 'string', 'max:20'],
            'id_front_path' => ['nullable', 'string', 'max:255'],
            'id_back_path' => ['nullable', 'string', 'max:255'],
            'proof_of_address_path' => ['nullable', 'string', 'max:255'],
            'house_photos_complete' => ['sometimes', 'boolean'],
        ];
    }
}
