<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Puente efimero entre el login (password correcto) y la verificacion de
 * OTP. La API es sin estado (Sanctum), asi que este "challenge" reemplaza
 * a la sesion PHP que usaria una app web tradicional: guarda en cache que
 * usuario esta a medio autenticar, identificado por un id opaco que se
 * entrega al cliente en vez del token final.
 */
final class MfaChallengeStore
{
    public function create(int $userId, ?string $channel): string
    {
        $challengeId = Str::random(40);

        Cache::put(
            self::key($challengeId),
            ['user_id' => $userId, 'channel' => $channel],
            now()->addMinutes((int) config('otp.challenge_ttl_minutes', 10))
        );

        return $challengeId;
    }

    /**
     * @return array{user_id: int, channel: ?string}|null
     */
    public function get(string $challengeId): ?array
    {
        $payload = Cache::get(self::key($challengeId));

        if (! is_array($payload) || ! isset($payload['user_id'])) {
            return null;
        }

        return [
            'user_id' => (int) $payload['user_id'],
            'channel' => isset($payload['channel']) ? (string) $payload['channel'] : null,
        ];
    }

    public function forget(string $challengeId): void
    {
        Cache::forget(self::key($challengeId));
    }

    private static function key(string $challengeId): string
    {
        return "mfa_challenge:{$challengeId}";
    }
}
