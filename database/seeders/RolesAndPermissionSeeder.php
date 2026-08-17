<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

final class RolesAndPermissionSeeder extends Seeder
{
    /**
     * Catalogo de roles de negocio (tabla propia `roles`, sin Spatie).
     */
    public function run(): void
    {
        $roles = [
            'administrator' => 'Administrador',
            'general_manager' => 'Gerente General',
            'branch_manager' => 'Gerente de Sucursal',
            'coordinator' => 'Coordinador',
            'verifier' => 'Verificador',
            'cashier' => 'Cajera',
            'distributor' => 'Distribuidora',
        ];

        foreach ($roles as $code => $name) {
            Role::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_active' => true]
            );
        }
    }
}
