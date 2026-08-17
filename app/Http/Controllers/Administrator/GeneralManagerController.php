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
            ['code' => 'general_manager'],
            ['name' => 'Gerente General', 'is_active' => true]
        );

        $user = DB::transaction(function () use ($request, $role): User {
            $person = Person::query()->create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
            ]);

            return User::query()->create([
                'person_id' => $person->id,
                'username' => $request->username,
                'password_hash' => Hash::make($request->password),
                'role_id' => $role->id,
                'branch_id' => null,
                'is_active' => true,
            ]);
        });

        $audit->record($request, 'GENERAL_MANAGER_CREATED', 'users', 'Gerente general creado.', null, ['user_id' => $user->id]);

        return $this->created(
            new UserResource($user->load(['person', 'role', 'branch'])),
            'Gerente general creado exitosamente'
        );
    }
}
