<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
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

        // Create Branch (Sucursal Matriz)
        $matrizBranch = Branch::create([
            'name' => 'Sucursal Matriz',
            'code' => 'BR-MATRIZ',
            'address' => 'Av. Constitucion 123, Centro, Monterrey, NL, 64000',
            'phone' => '8112345678',
            'is_active' => true,
        ]);

        // Create/Find Person record for General Manager
        $gmPerson = Person::query()->firstOrCreate(
            ['email' => 'generalmanager@example.com'],
            [
                'first_name' => 'Gerente',
                'last_name' => 'General',
                'gender' => 'M',
                'birth_date' => '1985-05-10',
                'street' => 'Av. Constitucion',
                'external_number' => '123',
                'city' => 'Monterrey',
                'state' => 'Nuevo Leon',
                'postal_code' => '64000',
            ]
        );

        // Find or create User for General Manager matching username
        $gmUser = User::query()->firstOrCreate(
            ['username' => 'generalmanager'],
            [
                'person_id' => $gmPerson->id,
                'password_hash' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        // Assign General Manager role globally using Spatie
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
        $gmUser->assignRole('general_manager');


        // -------------------------------------------------------------
        // 2. Create Sucursal Secundaria & Branch Manager
        // -------------------------------------------------------------

        // Create Branch (Sucursal Secundaria)
        $subsidiaryBranch = Branch::create([
            'name' => 'Sucursal Secundaria',
            'code' => 'BR-SECUNDARIA',
            'address' => 'Paseo de la Reforma 456, Juarez, CDMX, 06600',
            'phone' => '5512345678',
            'is_active' => true,
        ]);

        // Create/Find Person record for Branch Manager
        $bmPerson = Person::query()->firstOrCreate(
            ['email' => 'branch.manager@example.com'],
            [
                'first_name' => 'Gerente',
                'last_name' => 'Sucursal',
                'gender' => 'F',
                'birth_date' => '1990-08-20',
                'street' => 'Paseo de la Reforma',
                'external_number' => '456',
                'city' => 'CDMX',
                'state' => 'CDMX',
                'postal_code' => '06600',
            ]
        );

        // Find or create User for Branch Manager matching username
        $bmUser = User::query()->firstOrCreate(
            ['username' => 'branch.manager'],
            [
                'person_id' => $bmPerson->id,
                'password_hash' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        // Assign Branch Manager role scoped to the branch using Spatie Teams
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($subsidiaryBranch->id);
        $bmUser->assignRole('branch_manager');
    }
}
