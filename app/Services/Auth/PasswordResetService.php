<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Recuperacion de contrasena por enlace enviado al correo de la persona.
 *
 * El usuario del sistema se autentica por username/password_hash (no tiene
 * columna `email`); el correo vive en `people.email`. Por eso el lookup
 * replica el mismo criterio que AuthController::login() (username o
 * person.email) y el token se guarda contra el correo de la persona, que es
 * el unico canal donde se puede entregar el enlace.
 */
final class PasswordResetService
{
    /**
     * Busca al usuario y, si tiene correo registrado, le envia el enlace de
     * recuperacion. Es intencional que no distinga entre "no existe" y "no
     * tiene correo" hacia afuera: el llamador siempre responde el mismo
     * mensaje generico para no revelar que usuarios existen en el sistema.
     */
    public function sendResetLink(string $identifier): ?User
    {
        $user = User::query()
            ->with('person')
            ->where('username', $identifier)
            ->orWhereHas('person', function ($query) use ($identifier): void {
                $query->where('email', $identifier);
            })
            ->first();

        if (! $user || ! $user->is_active || ! $user->person?->email) {
            return null;
        }

        $email = $user->person->email;
        $rawToken = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($rawToken), 'created_at' => now()]
        );

        $resetUrl = rtrim((string) config('app.frontend_url'), '/').'/reset-password?token='.$rawToken.'&email='.urlencode($email);

        Mail::to($email)->send(new PasswordResetMail(
            recipientName: trim(($user->person->first_name ?? '').' '.($user->person->last_name ?? '')) ?: $user->username,
            resetUrl: $resetUrl,
            expiresInMinutes: (int) config('auth.passwords.users.expire', 60),
        ));

        return $user;
    }

    /**
     * Valida el token contra `password_reset_tokens` (mismo expire que el
     * resto de la app, config('auth.passwords.users.expire')) y, si es
     * valido, actualiza la contrasena y revoca todas las sesiones activas del
     * usuario (igual que el reset del propio starter kit / changePassword
     * no lo hace, pero aqui si aplica: si alguien mas tenia una sesion abierta
     * con la contrasena anterior, debe cerrarse).
     */
    public function reset(string $email, string $token, string $password): ?User
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record || ! Hash::check($token, $record->token)) {
            return null;
        }

        $expireMinutes = (int) config('auth.passwords.users.expire', 60);
        $expiresAt = Carbon::parse($record->created_at)->addMinutes($expireMinutes);
        if (now()->greaterThan($expiresAt)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return null;
        }

        $user = User::query()->whereHas('person', function ($query) use ($email): void {
            $query->where('email', $email);
        })->first();

        if (! $user) {
            return null;
        }

        $user->update(['password_hash' => Hash::make($password), 'password_confirmed_at' => now()]);
        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return $user;
    }
}
