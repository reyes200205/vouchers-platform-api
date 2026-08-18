<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $second_last_name
 * @property string|null $gender
 * @property string|null $birth_date
 * @property string $curp
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
 * @property string $username
 * @property string $password
 * @property string $role_code
 * @property int $branch_id
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
            'second_last_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'in:M,F,OTHER'],
            'birth_date' => ['nullable', 'date'],
            'curp' => ['required', 'string', 'size:18', 'unique:people,curp'],
            'rfc' => ['nullable', 'string', 'max:13', 'unique:people,rfc'],
            'home_phone' => ['nullable', 'string', 'max:20'],
            'mobile_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'street' => ['nullable', 'string', 'max:150'],
            'external_number' => ['nullable', 'string', 'max:30'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'username' => ['required', 'string', 'max:80', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
            'role_code' => ['required', 'string', 'max:50', 'exists:roles,code'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ];
    }
}