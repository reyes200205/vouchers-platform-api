<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\LoginChannel;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\BranchSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Financial\FinancialCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthController extends ApiController
{
    public function login(LoginRequest $request, AuditLogger $audit, FinancialCalculationService $financial): JsonResponse
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

        $this->attachPreValeMaxAmount($user, $financial);

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

    public function me(Request $request, FinancialCalculationService $financial): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['person', 'businessRoles', 'distributor.category']);

        $this->attachPreValeMaxAmount($user, $financial);

        return $this->success(new UserResource($user));
    }

    /**
     * Calcula el monto maximo que la distribuidora puede pedir en su proximo
     * vale por la regla del pre-vale (50% del credito disponible + tolerancia,
     * cuando tiene el 100% disponible o le acaban de aumentar su linea) y lo
     * deja pegado al modelo para que UserResource lo exponga. Se calcula aqui
     * -y no se duplica en el frontend- para que la lista de vales que puede
     * elegir la distribuidora (app/pages/distributor-portal/configure_vale)
     * siempre respete exactamente la misma regla que valida el backend al
     * recibir la solicitud.
     */
    private function attachPreValeMaxAmount(User $user, FinancialCalculationService $financial): void
    {
        $distributor = $user->distributor;
        if (! $distributor || ! $distributor->branch_id) {
            return;
        }

        $branchSetting = BranchSetting::query()
            ->firstOrCreate(['branch_id' => $distributor->branch_id])
            ->refresh();

        $result = $financial->validatePreVale(
            requestedAmount: 0.0,
            availableCredit: (float) $distributor->available_credit,
            totalCreditLimit: (float) $distributor->credit_limit,
            maxPercentage: (float) $branchSetting->pre_vale_max_percentage,
            toleranceAmount: (float) $branchSetting->pre_vale_tolerance_amount,
            reactivationPending: $distributor->prevale_required_after_credit_increase_at !== null,
        );

        $distributor->setAttribute('pre_vale_max_amount', $result->ruleApplied ? $result->maxAllowedAmount : null);
    }
}
