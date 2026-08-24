<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Rules\ValidCurp;
use App\Rules\ValidRfc;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property bool $is_active
 * @property string|null $role_code
 * @property int|null $branch_id
 * @property string|null $first_name
 * @property string|null $middle_name
 * @property string|null $last_name
 * @property string|null $second_last_name
 * @property string|null $gender
 * @property string|null $birth_date
 * @property string|null $curp
 * @property string|null $rfc
 * @property string|null $home_phone
 * @property string|null $mobile_phone
 * @property string|null $email
 * @property string|null $street
 * @property string|null $external_number
 * @property string|null $neighborhood
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 */
final class UpdateStaffRequest extends FormRequest
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
        $person = $this->route('user')?->person;

        return [
            'is_active' => ['required', 'boolean'],
            'role_code' => ['sometimes', 'string', 'max:50', 'exists:roles,code'],
            'branch_id' => ['sometimes', 'integer', 'exists:branches,id'],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'second_last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'gender' => ['sometimes', 'nullable', 'in:M,F,OTHER'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:18 years ago'],
            'curp' => ['sometimes', 'nullable', 'string', 'size:18', new ValidCurp(), Rule::unique('people', 'curp')->ignore($person?->id)],
            'rfc' => ['sometimes', 'nullable', 'string', 'max:13', new ValidRfc(), Rule::unique('people', 'rfc')->ignore($person?->id)],
            'home_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'mobile_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'street' => ['sometimes', 'nullable', 'string', 'max:150'],
            'external_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'neighborhood' => ['sometimes', 'nullable', 'string', 'max:120'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'state' => ['sometimes', 'nullable', 'string', 'max:120'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:10'],
        ];
    }
}
