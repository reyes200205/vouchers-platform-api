<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Branch;
use App\Models\Role;
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
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $originalTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($this->id);

        $managerUser = Role::where('name', 'branch_manager')->where('guard_name', 'web')->exists()
            ? User::role('branch_manager')->first()
            : null;
        if ($managerUser) {
            $managerUser->loadMissing('person');
            $manager = [
                'id' => $managerUser->id,
                'username' => $managerUser->username,
                'name' => $managerUser->person ? trim($managerUser->person->first_name.' '.$managerUser->person->last_name) : $managerUser->username,
            ];
        }
        $registrar->setPermissionsTeamId($originalTeamId);

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
