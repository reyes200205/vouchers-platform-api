<?php

declare(strict_types=1);

namespace App\Services\Employees;

use App\Models\Employee;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
     *     role: int
     * } $data
     */
    // NOTA: este servicio sigue roto de forma preexistente porque `App\Models\Employee`
    // y la tabla `employees` no existen en el proyecto. Fuera de alcance de esta tarea.
    public function execute(array $data): Employee
    {
        return DB::transaction(static function () use ($data): Employee {
            $fullName = trim($data['first_name'] . ' ' . $data['last_name']);
            $randomPassword = Str::random(16);
            $role = Role::query()->findOrFail($data['role']);

            // 1. Create user with a random password
            $user = User::create([
                'name' => $fullName,
                'email' => $data['email'],
                'password' => Hash::make($randomPassword),
            ]);

            $user->businessRoles()->attach($role, [
                'branch_id' => $data['branch_id'],
                'assigned_at' => now(),
                'is_primary' => true,
            ]);

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
