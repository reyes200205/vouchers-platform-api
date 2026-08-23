<?php

declare(strict_types=1);

namespace App\Http\Requests\Applications;

use App\Models\Application;
use App\Rules\ValidCurp;
use App\Rules\ValidRfc;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateApplicationRequest extends FormRequest
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
        /** @var Application $application */
        $application = $this->route('application');

        return [
            'person' => ['sometimes', 'array'],
            'person.first_name' => ['sometimes', 'string', 'max:100'],
            'person.middle_name' => ['nullable', 'string', 'max:100'],
            'person.last_name' => ['sometimes', 'string', 'max:100'],
            'person.second_last_name' => ['sometimes', 'string', 'max:100'],
            'person.gender' => ['sometimes', 'in:M,F,OTHER'],
            'person.birth_date' => ['sometimes', 'date', 'before_or_equal:18 years ago'],
            'person.curp' => ['sometimes', 'string', 'size:18', new ValidCurp(), Rule::unique('people', 'curp')->ignore($application->applicant_person_id)],
            'person.rfc' => ['sometimes', 'string', 'size:13', new ValidRfc(), Rule::unique('people', 'rfc')->ignore($application->applicant_person_id)],
            'person.home_phone' => ['sometimes', 'string', 'max:30'],
            'person.mobile_phone' => ['sometimes', 'string', 'max:30'],
            'person.email' => ['sometimes', 'email', 'max:150'],
            'person.street' => ['sometimes', 'string', 'max:150'],
            'person.external_number' => ['sometimes', 'string', 'max:30'],
            'person.neighborhood' => ['sometimes', 'string', 'max:120'],
            'person.city' => ['sometimes', 'string', 'max:120'],
            'person.state' => ['sometimes', 'string', 'max:120'],
            'person.postal_code' => ['sometimes', 'string', 'max:10'],
            'person.notes' => ['nullable', 'string'],
            'person.street_references' => ['nullable', 'string'],
            'family_data' => ['sometimes', 'array'],
            'family_data.applicant_age' => ['sometimes', 'integer', 'min:18'],
            'vehicles' => ['nullable', 'array'],
            'requested_credit_limit' => ['sometimes', 'decimal:0,2', 'min:1000'],
        ];
    }
}
