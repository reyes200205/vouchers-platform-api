<?php

declare(strict_types=1);

namespace App\Http\Requests\Applications;

use App\Rules\ValidCurp;
use App\Rules\ValidRfc;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

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
            'person.second_last_name' => ['required', 'string', 'max:100'],
            'person.gender' => ['required', 'in:M,F,OTHER'],
            'person.birth_date' => ['required', 'date', 'before_or_equal:18 years ago'],
            'person.curp' => ['required', 'string', 'size:18', new ValidCurp(), 'unique:people,curp'],
            'person.rfc' => ['required', 'string', 'size:13', new ValidRfc(), 'unique:people,rfc'],
            'person.home_phone' => ['required', 'string', 'regex:/^\d{10}$/', 'unique:people,home_phone'],
            'person.mobile_phone' => ['required', 'string', 'regex:/^\d{10}$/', 'unique:people,mobile_phone'],
            'person.email' => ['required', 'email', 'max:150', 'unique:people,email'],
            'person.street' => ['required', 'string', 'max:150'],
            'person.external_number' => ['required', 'string', 'regex:/^\d+$/', 'max:30'],
            'person.neighborhood' => ['required', 'string', 'max:120'],
            'person.city' => ['required', 'string', 'max:120'],
            'person.state' => ['required', 'string', 'max:120'],
            'person.postal_code' => ['required', 'string', 'regex:/^\d{5}$/'],
            'person.latitude' => ['nullable', 'decimal:0,7'],
            'person.longitude' => ['nullable', 'decimal:0,7'],
            'person.notes' => ['nullable', 'string'],
            'person.street_references' => ['nullable', 'string'],
            // family_data agrupa: familiares/conyuge, ocupacion (donde trabaja o estudia)
            // y vivienda (tenencia, dimensiones, referencia laboral). Ver new.vue en el frontend
            // para la forma exacta que arma el coordinador.
            'family_data' => ['required', 'array'],
            'family_data.members' => ['nullable', 'array'],
            'family_data.members.*.name' => ['nullable', 'string', 'max:150'],
            'family_data.members.*.relationship' => ['nullable', 'string', 'max:50'],
            'family_data.members.*.phone' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'family_data.members.*.age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'family_data.occupation' => ['required', 'array'],
            'family_data.occupation.type' => ['required', 'string', 'max:100'],
            'family_data.occupation.place_name' => ['required', 'string', 'max:150'],
            'family_data.occupation.position' => ['required', 'string', 'max:100'],
            'family_data.occupation.phone' => ['required', 'string', 'regex:/^\d{10}$/'],
            // El tope real (antiguedad <= edad - 18) depende de person.birth_date,
            // que no se puede expresar como regla estatica -- ver withValidator().
            'family_data.occupation.years' => ['required', 'integer', 'min:0'],
            'family_data.occupation.monthly_income' => ['required', 'numeric', 'min:0'],
            'family_data.housing' => ['required', 'array'],
            'family_data.housing.ownership_type' => ['required', 'string', 'max:50'],
            'family_data.housing.dimensions' => ['required', 'string', 'max:100'],
            // Mismo caso: el tope (<= edad actual) se valida en withValidator().
            'family_data.housing.years_at_address' => ['required', 'integer', 'min:0'],
            'family_data.housing.work_reference' => ['required', 'array'],
            'family_data.housing.work_reference.name' => ['required', 'string', 'max:150'],
            'family_data.housing.work_reference.phone' => ['required', 'string', 'regex:/^\d{10}$/'],
            'vehicles' => ['nullable', 'array'],
            'vehicles.*.brand' => ['nullable', 'string', 'max:100'],
            'vehicles.*.model' => ['nullable', 'string', 'max:100'],
            // El tope (<= año actual + 2) tampoco es estatico -- ver withValidator().
            'vehicles.*.year' => ['nullable', 'string', 'regex:/^\d{4}$/'],
            'vehicles.*.plates' => ['nullable', 'string', 'max:20'],
            'requested_credit_limit' => ['required', 'decimal:0,2', 'min:1000'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'initial_category_code' => ['sometimes', 'string', 'max:20'],
            'id_front_path' => ['nullable', 'string', 'max:255'],
            'id_back_path' => ['nullable', 'string', 'max:255'],
            'proof_of_address_path' => ['nullable', 'string', 'max:255'],
            'house_photos_complete' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'person.home_phone.regex' => 'El teléfono debe tener exactamente 10 dígitos, sin espacios ni letras.',
            'person.home_phone.unique' => 'Ya existe un registro con este número de teléfono.',
            'person.mobile_phone.regex' => 'El celular debe tener exactamente 10 dígitos, sin espacios ni letras.',
            'person.mobile_phone.unique' => 'Ya existe un registro con este número de celular.',
            'person.email.unique' => 'Ya existe un registro con este correo electrónico.',
            'person.external_number.regex' => 'El número exterior solo puede contener dígitos, sin letras ni espacios.',
            'person.postal_code.regex' => 'El código postal debe tener exactamente 5 dígitos, sin letras.',
            'family_data.members.*.phone.regex' => 'El teléfono del familiar debe tener exactamente 10 dígitos, sin espacios ni letras.',
            'family_data.occupation.phone.regex' => 'El teléfono del trabajo o escuela debe tener exactamente 10 dígitos, sin espacios ni letras.',
            'family_data.housing.work_reference.phone.regex' => 'El teléfono de la referencia laboral debe tener exactamente 10 dígitos, sin espacios ni letras.',
            'vehicles.*.year.regex' => 'El año del vehículo debe ser un número de 4 dígitos.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $birthDate = $this->input('person.birth_date');
            $age = $birthDate ? Carbon::parse($birthDate)->age : null;

            // La antiguedad en el trabajo no puede ser mayor a los años que
            // el solicitante lleva siendo mayor de edad (edad - 18): antes de
            // eso no pudo haber generado antiguedad laboral formal.
            $occupationYears = $this->input('family_data.occupation.years');
            if ($age !== null && $occupationYears !== null) {
                $maxOccupationYears = max(0, $age - 18);
                if ((int) $occupationYears > $maxOccupationYears) {
                    $validator->errors()->add(
                        'family_data.occupation.years',
                        "La antigüedad no puede ser mayor a {$maxOccupationYears} años: el solicitante tiene {$age} años y es mayor de edad desde hace {$maxOccupationYears}."
                    );
                }
            }

            // Los años viviendo en el domicilio no pueden superar la edad del
            // solicitante (pudo vivir ahi desde que nacio, no antes).
            $housingYears = $this->input('family_data.housing.years_at_address');
            if ($age !== null && $housingYears !== null && (int) $housingYears > $age) {
                $validator->errors()->add(
                    'family_data.housing.years_at_address',
                    "Los años viviendo en el domicilio no pueden ser mayores a la edad del solicitante ({$age} años)."
                );
            }

            // Un vehiculo no puede tener modelo de mas de 2 años a futuro
            // (los fabricantes anuncian el modelo del año siguiente con
            // anticipacion, pero nunca mas de uno o dos años).
            $maxVehicleYear = (int) now()->format('Y') + 2;
            foreach ((array) $this->input('vehicles', []) as $index => $vehicle) {
                $year = is_array($vehicle) ? ($vehicle['year'] ?? null) : null;
                if ($year !== null && $year !== '' && (int) $year > $maxVehicleYear) {
                    $validator->errors()->add(
                        "vehicles.{$index}.year",
                        "El año del vehículo no puede ser mayor a {$maxVehicleYear}."
                    );
                }
            }
        });
    }
}
