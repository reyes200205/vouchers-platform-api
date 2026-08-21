<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\OtpVerificationResult;
use App\Mail\OtpCodeMail;
use App\Models\OneTimePassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Genera, envia por correo y verifica codigos de un solo uso (OTP) usados
 * como segundo factor de autenticacion. El codigo nunca se persiste en
 * texto plano (ver OneTimePassword::code_hash).
 */
final class OneTimePasswordService
{
    /**
     * Invalida cualquier codigo activo del usuario, genera uno nuevo y lo
     * envia por correo. Un fallo de envio no debe tumbar el flujo de login,
     * asi que se registra en logs (mismo patron que ApproveVoucherService).
     */
    public function generateAndSend(User $user): void
    {
        OneTimePassword::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $length = (int) config('otp.code_length', 6);
        $code = mb_str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $expiresInMinutes = (int) config('otp.expires_in_minutes', 5);

        OneTimePassword::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ]);

        $email = $user->person?->email;

        if ($email === null) {
            Log::error('No se pudo enviar el codigo OTP: el usuario no tiene correo registrado.', [
                'user_id' => $user->id,
            ]);

            return;
        }

        try {
            Mail::to($email)->send(new OtpCodeMail(
                recipientName: $user->person?->first_name ?? $user->username,
                code: $code,
                expiresInMinutes: $expiresInMinutes,
            ));
        } catch (Throwable $e) {
            Log::error('No se pudo enviar el correo con el codigo OTP.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function verify(User $user, string $code): OtpVerificationResult
    {
        $otp = OneTimePassword::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($otp === null) {
            return OtpVerificationResult::NOT_FOUND;
        }

        $maxAttempts = (int) config('otp.max_attempts', 5);

        if ($otp->attempts >= $maxAttempts) {
            return OtpVerificationResult::RATE_LIMITED;
        }

        if ($otp->expires_at->isPast()) {
            return OtpVerificationResult::EXPIRED;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return OtpVerificationResult::INCORRECT;
        }

        $otp->update(['consumed_at' => now()]);

        return OtpVerificationResult::OK;
    }
}
