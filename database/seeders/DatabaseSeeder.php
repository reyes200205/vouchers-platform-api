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
        // BranchAndEmployeeSeeder queda fuera del seeding automatico por dependencias no existentes.
        // Ahora UserSeeder crea unicamente al Super Admin y se incluye en el seeding.
        // AlessandroDemoSeeder queda fuera: usa Person::factory()/Customer::factory()/etc,
        // que dependen de fake() (fakerphp/faker esta en require-dev, no en el servidor
        // con `composer install --no-dev`). El sistema jala igual sin datos de demo:
        // RolesAndPermissionSeeder + UserSeeder ya dejan el Super Admin listo para entrar.
        $this->call([
            RolesAndPermissionSeeder::class,
            UserSeeder::class,
            // AlessandroDemoSeeder::class,
        ]);
    }
}
