<?php

declare(strict_types=1);

namespace App\Http\Requests\Employees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string $email
 * @property string $first_name
 * @property string $last_name
 * @property string $birth_date
 * @property string $gender
 * @property int $branch_id
 * @property string $employee_code
 * @property string $position
 * @property string $status
 */
final class StoreEmployeeRequest extends FormRequest
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
            // User validation rules
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],

            // Person validation rules
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'date_format:Y-m-d'],
            'gender' => ['required', 'string', 'in:male,female,other'],

            // Employee validation rules
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'employee_code' => ['required', 'string', 'max:255', 'unique:employees,employee_code'],
            'position' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'role' => ['required', 'string', 'exists:roles,name'],
        ];
    }
}
