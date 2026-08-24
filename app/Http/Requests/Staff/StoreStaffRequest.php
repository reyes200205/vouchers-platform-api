<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Rules\ValidCurp;
use App\Rules\ValidRfc;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string $second_last_name
 * @property string $gender
 * @property string $birth_date
 * @property string $curp
 * @property string $rfc
 * @property string|null $home_phone
 * @property string $mobile_phone
 * @property string $email
 * @property string $street
 * @property string $external_number
 * @property string $neighborhood
 * @property string $city
 * @property string $state
 * @property string $postal_code
 * @property string $username
 * @property string $password
 * @property string $role_code
 * @property int|null $branch_id
 */
final class StoreStaffRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'second_last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:M,F,OTHER'],
            'birth_date' => ['required', 'date', 'before_or_equal:18 years ago'],
            'curp' => ['required', 'string', 'size:18', new ValidCurp(), 'unique:people,curp'],
            'rfc' => ['required', 'string', 'max:13', new ValidRfc(), 'unique:people,rfc'],
            'home_phone' => ['nullable', 'string', 'max:20'],
            'mobile_phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:150'],
            'street' => ['required', 'string', 'max:150'],
            'external_number' => ['required', 'string', 'max:30'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:10'],
            'username' => ['required', 'string', 'max:80', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
            'role_code' => ['required', 'string', 'max:50', 'exists:roles,code'],
            'branch_id' => [
                Rule::requiredIf(fn () => $this->input('role_code') !== 'general_manager'),
                'nullable',
                'integer',
                'exists:branches,id',
            ],
        ];
    }
}
