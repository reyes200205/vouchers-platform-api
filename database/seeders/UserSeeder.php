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
                ]
            );

            // Assign the role to the user globally (without branch scope)
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
            $user->syncRoles([$userData['role']]);
        }
    }
}
