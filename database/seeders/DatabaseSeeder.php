<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // UserSeeder y BranchAndEmployeeSeeder quedan fuera del seeding automatico:
        // dependen de modelos/columnas que no existen en el esquema real (Employee,
        // Address, users.name/email/password). El primer administrador se crea con
        // el comando `php artisan app:create-admin`.
        $this->call([
            // RolesAndPermissionSeeder::class,
            // UserSeeder::class,
            // BranchAndEmployeeSeeder::class,
            AlessandroDemoSeeder::class,
        ]);
    }
}
