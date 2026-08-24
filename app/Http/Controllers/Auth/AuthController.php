<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\AuditEventType;
use App\Enums\LoginChannel;
use App\Enums\OtpVerificationResult;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResendMfaRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyMfaRequest;
use App\Http\Resources\UserResource;
use App\Models\BranchSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\MfaChallengeStore;
use App\Services\Auth\PasswordResetService;
use App\Services\Financial\FinancialCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthController extends ApiController
{
    public function login(LoginRequest $request, AuditLogger $audit, FinancialCalculationService $financial, MfaChallengeStore $challenges): JsonResponse
    {
        $user = User::query()
            ->with(['person', 'businessRoles', 'distributor.category'])
            ->where('username', $request->username)
            ->orWhereHas('person', function ($query) use ($request) {
                $query->where('email', $request->username);
            })
            ->first();

        if (! $user || ! $user->is_active || ! Hash::check($request->password, $user->getAuthPassword())) {
            return $this->unauthorized('Credenciales invalidas.');
        }

        if ($user->requiresOtp()) {
            $challengeId = $challenges->create($user->id, $request->channel);
            $user->sendOneTimePassword();

            // AuditLogger lee el actor desde $request->user(); el usuario aun no
            // tiene token, asi que lo forzamos temporalmente para poder auditar.
            Auth::setUser($user);
            $audit->record($request, AuditEventType::Sent, 'auth', 'Codigo OTP enviado para segundo factor de autenticacion.', $user->activeBusinessBranchIds()[0] ?? null, $this->getUserAuditData($user));

            return $this->success([
                'requires_otp' => true,
                'challenge_id' => $challengeId,
                'masked_email' => self::maskEmail($user->person?->email),
            ], 'OTP verification required');
        }

        return $this->finishLogin($user, $request, $audit, $financial, $request->channel);
    }

    public function verifyMfa(VerifyMfaRequest $request, AuditLogger $audit, FinancialCalculationService $financial, MfaChallengeStore $challenges): JsonResponse
    {
        $challenge = $challenges->get($request->challenge_id);

        if ($challenge === null) {
            return $this->unauthorized('Challenge expired or invalid. Please log in again.');
        }

        $user = User::query()
            ->with(['person', 'businessRoles', 'distributor.category'])
            ->findOrFail($challenge['user_id']);

        Auth::setUser($user);
        $result = $user->consumeOneTimePassword($request->code);

        if (! $result->isOk()) {
            $audit->record(
                $request,
                AuditEventType::Failed,
                'auth',
                'Intento fallido de verificacion OTP: '.$result->value.'.',
                $user->activeBusinessBranchIds()[0] ?? null,
                $this->getUserAuditData($user, ['reason' => $result->value]),
                'WARNING'
            );

            return $this->error($this->mfaErrorMessage($result), 422);
        }

        $challenges->forget($request->challenge_id);

        $audit->record($request, AuditEventType::Verified, 'auth', 'Segundo factor verificado exitosamente.', $user->activeBusinessBranchIds()[0] ?? null, $this->getUserAuditData($user));

        return $this->finishLogin($user, $request, $audit, $financial, $challenge['channel']);
    }

    public function resendMfa(ResendMfaRequest $request, AuditLogger $audit, MfaChallengeStore $challenges): JsonResponse
    {
        $challenge = $challenges->get($request->challenge_id);

        if ($challenge === null) {
            return $this->unauthorized('Challenge expired or invalid. Please log in again.');
        }

        $user = User::query()->with('person')->findOrFail($challenge['user_id']);
        $user->sendOneTimePassword();

        Auth::setUser($user);
        $audit->record($request, AuditEventType::Resent, 'auth', 'Reenvio de codigo OTP.', null, $this->getUserAuditData($user));

        return $this->success([
            'masked_email' => self::maskEmail($user->person?->email),
        ], 'A new OTP code was sent');
    }

    public function logout(Request $request, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        $token?->delete();

        $audit->record($request, AuditEventType::Logout, 'auth', 'Cierre de sesion.', $user->activeBusinessBranchIds()[0] ?? null, $this->getUserAuditData($user));

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

    public function changePassword(ChangePasswordRequest $request, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($request->current_password, $user->getAuthPassword())) {
            return $this->error('Current password is incorrect', 422);
        }

        $user->update([
            'password_hash' => Hash::make($request->password),
            'password_confirmed_at' => now(),
        ]);

        $audit->record($request, AuditEventType::Changed, 'auth', 'Cambio de contrasena por el propio usuario.', $user->activeBusinessBranchIds()[0] ?? null, $this->getUserAuditData($user));

        return $this->success(message: 'Password changed successfully');
    }

    /**
     * Para cuando el usuario decide QUEDARSE con la contrasena temporal
     * (CURP) que se le asigno al darlo de alta, en vez de cambiarla: el
     * modal de "primer login" del frontend llama esto en ese caso. No
     * cambia el hash, solo apaga la bandera que obliga a mostrar el modal.
     */
    public function confirmPassword(Request $request, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['password_confirmed_at' => now()]);

        $audit->record($request, AuditEventType::Confirmed, 'auth', 'El usuario conservo su contrasena temporal.', $user->activeBusinessBranchIds()[0] ?? null, $this->getUserAuditData($user));

        return $this->success(message: 'Password confirmed successfully');
    }

    /**
     * Envia el enlace de recuperacion si el usuario existe y tiene correo
     * registrado. Responde siempre el mismo mensaje generico (exista o no el
     * usuario) para no revelar que usuarios estan dados de alta en el sistema.
     */
    public function forgotPassword(ForgotPasswordRequest $request, AuditLogger $audit, PasswordResetService $service): JsonResponse
    {
        $user = $service->sendResetLink($request->username);

        if ($user) {
            // AuditLogger lee el actor desde $request->user(); aqui aun no hay
            // sesion, asi que lo forzamos temporalmente para dejar registrado
            // a quien se le envio el enlace (mismo patron que MFA_CHALLENGE_SENT).
            Auth::setUser($user);
            $audit->record($request, AuditEventType::Sent, 'auth', 'Enlace de recuperacion de contrasena enviado.', $user->activeBusinessBranchIds()[0] ?? null, $this->getUserAuditData($user));
        }

        return $this->success(message: 'Si el usuario existe, enviamos un enlace de recuperación a su correo registrado.');
    }

    public function resetPassword(ResetPasswordRequest $request, AuditLogger $audit, PasswordResetService $service): JsonResponse
    {
        $user = $service->reset($request->email, $request->token, $request->password);

        if (! $user) {
            return $this->error('El enlace de recuperación no es válido o ya expiró.', 400);
        }

        Auth::setUser($user);
        $audit->record($request, AuditEventType::Completed, 'auth', 'Contrasena restablecida mediante enlace de recuperacion.', $user->activeBusinessBranchIds()[0] ?? null, $this->getUserAuditData($user));

        return $this->success(message: 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.');
    }

    private static function maskEmail(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 3).'***@'.$domain;
    }

    /**
     * Cola compartida del login: emite el token, marca canal/ultimo acceso,
     * audita y responde. La usan tanto el login directo (roles sin OTP)
     * como verifyMfa() tras un codigo correcto.
     */
    private function finishLogin(User $user, Request $request, AuditLogger $audit, FinancialCalculationService $financial, ?string $channel): JsonResponse
    {
        $user->update([
            'login_channel' => $channel ?? LoginChannel::WEB->value,
            'last_login_at' => now(),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        Auth::setUser($user);
        $audit->record($request, AuditEventType::Login, 'auth', 'Inicio de sesion exitoso.', $user->activeBusinessBranchIds()[0] ?? null, $this->getUserAuditData($user));

        $this->attachPreValeMaxAmount($user, $financial);

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Login successful');
    }

    private function mfaErrorMessage(OtpVerificationResult $result): string
    {
        return match ($result) {
            OtpVerificationResult::EXPIRED => 'The verification code has expired.',
            OtpVerificationResult::RATE_LIMITED => 'Too many incorrect attempts. Please request a new code.',
            OtpVerificationResult::NOT_FOUND, OtpVerificationResult::INCORRECT => 'The verification code is incorrect.',
            OtpVerificationResult::OK => 'OK',
        };
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

    private function getUserAuditData(User $user, array $extra = []): array
    {
        $role = $user->businessRoles()->wherePivotNull('revoked_at')->first();

        return array_merge([
            'user_id' => $user->id,
            'username' => $user->username,
            'email' => $user->person?->email,
            'role' => $role?->code ?? $role?->name ?? null,
            'branch_id' => $role?->pivot?->branch_id ?? null,
        ], $extra);
    }
}
