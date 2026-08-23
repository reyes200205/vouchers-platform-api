<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            $user->tokens()->delete();

            return response()->json([
                'success' => false,
                'message' => 'Cuenta desactivada. Ponte en contacto con un administrador.',
            ], 401);
        }

        return $next($request);
    }
}
