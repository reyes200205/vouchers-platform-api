<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Users\StoreGeneralManagerRequest;
use App\Http\Resources\UserResource;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class GeneralManagerController extends ApiController
{
    public function store(StoreGeneralManagerRequest $request, AuditLogger $audit): JsonResponse
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'general_manager', 'guard_name' => 'web'],
            ['code' => 'general_manager', 'description' => 'Gerente General', 'is_active' => true]
        );

        $user = DB::transaction(function () use ($request, $role): User {
            $person = Person::query()->create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
            ]);

            $user = User::query()->create([
                'person_id' => $person->id,
                'username' => $request->username,
                'password_hash' => Hash::make($request->password),
                'is_active' => true,
            ]);

            $user->businessRoles()->attach($role, [
                'branch_id' => null,
                'assigned_at' => now(),
                'is_primary' => true,
            ]);

            return $user;
        });

        $audit->record($request, 'GENERAL_MANAGER_CREATED', 'users', 'Gerente general creado.', null, ['user_id' => $user->id]);

        return $this->created(
            new UserResource($user->load(['person', 'businessRoles'])),
            'Gerente general creado exitosamente'
        );
    }
}
