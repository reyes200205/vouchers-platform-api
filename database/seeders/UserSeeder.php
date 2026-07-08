<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'General Manager',
                'email' => 'general.manager@example.com',
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
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            // Assign the role to the user
            $user->syncRoles([$userData['role']]);
        }
    }
}
