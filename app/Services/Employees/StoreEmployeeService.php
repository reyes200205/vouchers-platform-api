<?php

declare(strict_types=1);

namespace App\Services\Employees;

use App\Models\Employee;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

final class StoreEmployeeService
{
    /**
     * @param array{
     *     email: string,
     *     first_name: string,
     *     last_name: string,
     *     birth_date: string,
     *     gender: string,
     *     branch_id: int,
     *     employee_code: string,
     *     position: string,
     *     status: string,
     *     role: string
     * } $data
     */
    public function execute(array $data): Employee
    {
        return DB::transaction(static function () use ($data): Employee {
            $fullName = trim($data['first_name'] . ' ' . $data['last_name']);
            $randomPassword = Str::random(16);

            // 1. Create user with a random password
            $user = User::create([
                'name' => $fullName,
                'email' => $data['email'],
                'password' => Hash::make($randomPassword),
            ]);

            // Assign the validated role from the database
            $user->assignRole($data['role']);

            // 2. Create person record
            $person = Person::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'full_name' => $fullName,
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
            ]);

            // 3. Create employee record
            return Employee::create([
                'user_id' => $user->id,
                'person_id' => $person->id,
                'branch_id' => $data['branch_id'],
                'employee_code' => $data['employee_code'],
                'position' => $data['position'],
                'status' => $data['status'],
            ]);
        });
    }
}
