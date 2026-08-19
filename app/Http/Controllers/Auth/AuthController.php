<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\LoginChannel;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthController extends ApiController
{
    public function login(LoginRequest $request, AuditLogger $audit): JsonResponse
    {
        $user = User::query()
            ->with(['person', 'businessRoles', 'distributor.category'])
            ->where('username', $request->username)
            ->orWhereHas('person', function ($query) use ($request) {
                $query->where('email', $request->username);
            })
            ->first();

        if (! $user || ! $user->is_active || ! Hash::check($request->password, $user->getAuthPassword())) {
            return $this->unauthorized('Invalid credentials');
        }

        $user->update([
            'login_channel' => $request->channel ?? LoginChannel::WEB->value,
            'last_login_at' => now(),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        // AuditLogger lee el actor desde $request->user(), que aun no esta resuelto
        // en esta request publica (el token recien se emitio arriba).
        auth()->setUser($user);
        $audit->record($request, 'LOGIN', 'auth', 'Inicio de sesion exitoso.', $user->activeBusinessBranchIds()[0] ?? null, ['user_id' => $user->id]);

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Login successful');
    }

    public function logout(Request $request, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        $token?->delete();

        $audit->record($request, 'LOGOUT', 'auth', 'Cierre de sesion.', $user->activeBusinessBranchIds()[0] ?? null, ['user_id' => $user->id]);

        return $this->success(message: 'Logged out successfully');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->success(new UserResource(
            $user->loadMissing(['person', 'businessRoles', 'distributor.category'])
        ));
    }
}
