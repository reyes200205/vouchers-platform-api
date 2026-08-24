<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Branch
 */
final class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $manager = null;

        // Usamos businessRoles() filtrando por revoked_at (igual que el resto del
        // sistema), no Spatie::role()/User::role(): ese scope nativo solo revisa
        // si existe una fila en model_has_roles para el rol+sucursal, ignorando
        // por completo revoked_at, asi que seguia mostrando como gerente a
        // alguien cuyo rol ya habia sido revocado desde el modulo de Staff.
        $managerUser = User::query()
            ->whereHas('businessRoles', fn ($q) => $q->where('roles.name', 'branch_manager')
                ->where('model_has_roles.branch_id', $this->id)
                ->whereNull('model_has_roles.revoked_at'))
            ->with('person')
            ->first();

        // Si no hay un branch_manager dedicado, mostramos al gerente general
        // que tiene esta sucursal como base -- pero SOLO cuando de verdad se
        // le asigno esa sucursal especifica (home_branch_id = esta sucursal),
        // nunca "por defecto" a un gerente general cualquiera.
        if ($managerUser === null) {
            $managerUser = User::query()
                ->where('home_branch_id', $this->id)
                ->whereHas('businessRoles', fn ($q) => $q->where('roles.name', 'general_manager')
                    ->whereNull('model_has_roles.revoked_at'))
                ->with('person')
                ->first();
        }

        if ($managerUser) {
            $manager = [
                'id' => $managerUser->id,
                'username' => $managerUser->username,
                'name' => $managerUser->person ? trim($managerUser->person->first_name.' '.$managerUser->person->last_name) : $managerUser->username,
            ];
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'manager' => $manager,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
