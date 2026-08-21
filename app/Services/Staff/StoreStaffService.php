<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class StoreStaffService
{
    /**
     * Crea un usuario de personal (persona + usuario + rol de negocio con sucursal).
     *
     * @param  array{
     *     first_name: string,
     *     middle_name?: string|null,
     *     last_name: string,
     *     second_last_name?: string|null,
     *     gender?: string|null,
     *     birth_date?: string|null,
     *     curp: string,
     *     rfc?: string|null,
     *     home_phone?: string|null,
     *     mobile_phone?: string|null,
     *     email?: string|null,
     *     street?: string|null,
     *     external_number?: string|null,
     *     neighborhood?: string|null,
     *     city?: string|null,
     *     state?: string|null,
     *     postal_code?: string|null,
     *     username: string,
     *     password: string,
     *     role_code: string,
     *     branch_id: int
     * }  $data
     */
    public function execute(User $actor, array $data): User
    {
        $role = Role::query()->where('code', $data['role_code'])->firstOrFail();

        if (! in_array($role->name, ListStaffService::STAFF_ROLES, true)) {
            abort(422, 'El rol seleccionado no es administrable desde el módulo de personal.');
        }

        if (! $actor->isGeneralManager() && ! $actor->hasRole('super-admin')) {
            abort_unless(
                in_array($role->name, ListStaffService::BRANCH_MANAGER_ROLES, true),
                403,
                'Solo el gerente general puede crear personal de otro tipo.'
            );
            abort_unless(in_array($data['branch_id'], $actor->activeBusinessBranchIds(), true), 403, 'Solo puedes asignar personal a tus sucursales.');
        }

        return DB::transaction(function () use ($data, $role): User {
            $person = Person::query()->create([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'second_last_name' => $data['second_last_name'] ?? null,
                'gender' => $data['gender'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'curp' => $data['curp'],
                'rfc' => $data['rfc'] ?? null,
                'home_phone' => $data['home_phone'] ?? null,
                'mobile_phone' => $data['mobile_phone'] ?? null,
                'email' => $data['email'] ?? null,
                'street' => $data['street'] ?? null,
                'external_number' => $data['external_number'] ?? null,
                'neighborhood' => $data['neighborhood'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
            ]);

            $user = User::query()->create([
                'person_id' => $person->id,
                'username' => $data['username'],
                'password_hash' => Hash::make($data['password']),
                'is_active' => true,
            ]);

            $user->businessRoles()->attach($role, [
                'branch_id' => $data['branch_id'],
                'assigned_at' => now(),
                'is_primary' => true,
            ]);

            return $user->load(['person', 'businessRoles']);
        });
    }
}