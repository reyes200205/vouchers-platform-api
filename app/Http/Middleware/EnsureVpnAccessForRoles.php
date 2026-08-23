<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe un endpoint a la red VPN interna, pero solo para los roles dados
 * (ej. general_manager, branch_manager). Un mismo endpoint puede recibir
 * peticiones de un rol que SI debe entrar solo por VPN (gerente) y de otro
 * que puede entrar por el canal publico (ej. customers.transfer.decide, que
 * tambien usa coordinador) — por eso los roles restringidos son parametro de
 * la ruta y no algo fijo aqui.
 *
 * El ocultamiento del lado del frontend (ver APPROVAL_RESTRICTED_ROLES en el
 * nuxt-app) es solo UX; esta es la validacion real. Si alguien le pega
 * directo a la API sin pasar por el frontend, esto sigue aplicando.
 */
final class EnsureVpnAccessForRoles
{
    public function handle(Request $request, Closure $next, string ...$roleCodes): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $hasRestrictedRole = $user->businessRoles()->whereIn('roles.name', $roleCodes)->exists();

        if (! $hasRestrictedRole || $this->isFromVpn($request)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Este recurso solo esta disponible desde la red VPN interna.',
        ], Response::HTTP_FORBIDDEN);
    }

    private function isFromVpn(Request $request): bool
    {
        $configured = config('network.vpn_cidrs', []);
        $cidrs = is_array($configured) ? array_values(array_filter($configured, 'is_string')) : [];

        // Sin rangos configurados no hay forma de verificar el origen: se
        // niega por seguridad en vez de dejar pasar por accidente (fail closed).
        if ($cidrs === []) {
            return false;
        }

        return IpUtils::checkIp((string) $request->ip(), $cidrs);
    }
}
