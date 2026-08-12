<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureBusinessAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasBusinessAbility($ability)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
