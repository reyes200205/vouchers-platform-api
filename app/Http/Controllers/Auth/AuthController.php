<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthController extends ApiController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->with(['person', 'businessRoles'])
            ->where('username', $request->username)
            ->first();

        if (! $user || ! $user->is_active || ! Hash::check($request->password, $user->getAuthPassword())) {
            return $this->unauthorized('Invalid credentials');
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        $token?->delete();

        return $this->success(message: 'Logged out successfully');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->success(new UserResource(
            $user->loadMissing(['person', 'businessRoles'])
        ));
    }
}
