<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureBusinessAbility
{
    public function handle(Request $request, Closure $next, string $ability, ?string $branchParameter = null): Response
    {
        $user = $request->user();
        $branch = $branchParameter === null ? null : $request->route($branchParameter);
        $branchId = is_object($branch)
            ? ($branch->branch_id ?? $branch->id)
            : (is_numeric($branch) ? (int) $branch : null);

        if ($user === null || ! $user->hasBusinessAbility($ability, $branchId)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
