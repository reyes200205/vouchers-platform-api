<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

final class RolesAndPermissionSeeder extends Seeder
{
    /**
     * Catalogo de roles de negocio (tabla `roles` de Spatie Permission).
     * `name` es el identificador usado por hasRole()/hasBusinessAbility() y debe
     * coincidir con los codigos usados en config/business-authorization.php.
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

        foreach ($roles as $code => $label) {
            Role::query()->firstOrCreate(
                ['name' => $code, 'guard_name' => 'web'],
                ['code' => $code, 'description' => $label, 'is_active' => true]
            );
        }
    }
}
