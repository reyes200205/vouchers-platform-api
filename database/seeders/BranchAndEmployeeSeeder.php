<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

final class BranchAndEmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // -------------------------------------------------------------
        // 1. Create Sucursal Matriz (Headquarters) & General Manager
        // -------------------------------------------------------------

        // Create Address for Matriz
        $matrizAddress = Address::create([
            'country' => 'Mexico',
            'state' => 'Nuevo Leon',
            'city' => 'Monterrey',
            'address' => 'Av. Constitucion 123, Centro',
            'postal_code' => '64000',
        ]);

        // Find or create User for General Manager
        $gmUser = User::query()->firstOrCreate(
            ['email' => 'generalmanager@example.com'],
            [
                'name' => 'Gerente General',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Assign General Manager role
        $gmRole = Role::findOrCreate('general_manager', 'web');
        $gmUser->assignRole($gmRole);

        // Create Person record for General Manager
        $gmPerson = Person::create([
            'first_name' => 'Gerente',
            'last_name' => 'General',
            'full_name' => 'Gerente General',
            'birth_date' => '1985-05-10',
            'gender' => 'male',
            'address_id' => $matrizAddress->id,
        ]);

        // Create Employee record (branch_id will be filled after branch creation)
        $gmEmployee = Employee::create([
            'user_id' => $gmUser->id,
            'person_id' => $gmPerson->id,
            'branch_id' => null,
            'employee_code' => 'EMP-001',
            'position' => 'General Manager',
            'status' => 'active',
        ]);

        // Create Branch (Sucursal Matriz)
        $matrizBranch = Branch::create([
            'name' => 'Sucursal Matriz',
            'branch_code' => 'BR-MATRIZ',
            'branch_type' => 'main_office',
            'manager_id' => $gmEmployee->id,
            'address_id' => $matrizAddress->id,
        ]);

        // Update employee branch_id
        $gmEmployee->update(['branch_id' => $matrizBranch->id]);


        // -------------------------------------------------------------
        // 2. Create Sucursal Secundaria & Branch Manager
        // -------------------------------------------------------------

        // Create Address for Subsidiary Office
        $subsidiaryAddress = Address::create([
            'country' => 'Mexico',
            'state' => 'CDMX',
            'city' => 'CDMX',
            'address' => 'Paseo de la Reforma 456, Juarez',
            'postal_code' => '06600',
        ]);

        // Find or create User for Branch Manager
        $bmUser = User::query()->firstOrCreate(
            ['email' => 'branch.manager@example.com'],
            [
                'name' => 'Gerente Sucursal',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Assign Branch Manager role
        $bmRole = Role::findOrCreate('branch_manager', 'web');
        $bmUser->assignRole($bmRole);

        // Create Person record for Branch Manager
        $bmPerson = Person::create([
            'first_name' => 'Gerente',
            'last_name' => 'Sucursal',
            'full_name' => 'Gerente Sucursal',
            'birth_date' => '1990-08-20',
            'gender' => 'female',
            'address_id' => $subsidiaryAddress->id,
        ]);

        // Create Employee record
        $bmEmployee = Employee::create([
            'user_id' => $bmUser->id,
            'person_id' => $bmPerson->id,
            'branch_id' => null,
            'employee_code' => 'EMP-002',
            'position' => 'Branch Manager',
            'status' => 'active',
        ]);

        // Create Branch (Sucursal Secundaria)
        $subsidiaryBranch = Branch::create([
            'name' => 'Sucursal Secundaria',
            'branch_code' => 'BR-SECUNDARIA',
            'branch_type' => 'subsidiary_office',
            'manager_id' => $bmEmployee->id,
            'address_id' => $subsidiaryAddress->id,
        ]);

        // Update employee branch_id
        $bmEmployee->update(['branch_id' => $subsidiaryBranch->id]);
    }
}
