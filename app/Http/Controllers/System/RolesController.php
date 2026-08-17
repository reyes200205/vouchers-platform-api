<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\ApiController;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RolesController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            RoleResource::collection($roles)->response()->getData(true)
        );
    }
}
