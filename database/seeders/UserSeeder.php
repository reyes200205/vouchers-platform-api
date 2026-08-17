<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'General Manager',
                'email' => 'generalmanager@example.com',
                'role' => 'general_manager',
            ],
            [
                'name' => 'Branch Manager',
                'email' => 'branch.manager@example.com',
                'role' => 'branch_manager',
            ],
            [
                'name' => 'Coordinator',
                'email' => 'coordinator@example.com',
                'role' => 'coordinator',
            ],
            [
                'name' => 'Verifier',
                'email' => 'verifier@example.com',
                'role' => 'verifier',
            ],
            [
                'name' => 'Cashier',
                'email' => 'cashier@example.com',
                'role' => 'cashier',
            ],
            [
                'name' => 'Distributor',
                'email' => 'distributor@example.com',
                'role' => 'distributor',
            ],
            [
                'name' => 'Administrator',
                'email' => 'administrator@example.com',
                'role' => 'administrator',
            ],
        ];

        foreach ($users as $userData) {
            $person = Person::query()->firstOrCreate(
                ['email' => $userData['email']],
                [
                    'first_name' => $userData['name'],
                    'last_name' => 'System',
                    'gender' => 'OTHER',
                ]
            );

            $user = User::query()->firstOrCreate(
                ['person_id' => $person->id],
                [
                    'username' => explode('@', $userData['email'])[0],
                    'password_hash' => Hash::make('password'),
                    'is_active' => true,
                ]
            );

            // Assign the role to the user globally (without branch scope)
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
            $user->syncRoles([$userData['role']]);
        }
    }
}
