<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class BaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Sucursal UTT (Matriz) in Torreón
        Branch::query()->firstOrCreate(
            ['code' => 'BR-UTT'],
            [
                'name' => 'Sucursal UTT',
                'address' => 'Torreón, Coahuila',
                'phone' => '8711234567',
                'is_active' => true,
            ]
        );

        // 2. Create Super Admin User
        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'jorgerenteriareyes4@gmail.com',
                'role' => 'super-admin',
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
                    'password_confirmed_at' => now(),
                ]
            );

            // Assign the role to the user globally (without branch scope)
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
            $user->syncRoles([$userData['role']]);
        }
    }
}
