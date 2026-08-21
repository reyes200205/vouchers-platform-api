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
        $branchId = $this->resolveBranchId($branch);

        if ($user === null || ! $user->hasBusinessAbility($ability, $branchId)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /**
     * Resolves the branch id to check against the user's roles.
     *
     * $branch->branch_id ?? $branch->id no distinguía entre "este modelo no
     * tiene columna branch_id" (ej. el propio Branch enlazado por ruta, donde
     * branch_id no existe como atributo y ?? cae correctamente a $branch->id)
     * y "este modelo SÍ tiene branch_id pero está en null" (ej. un
     * BankTransaction importado antes de que ImportBankDepositsService
     * empezara a guardarlo, o creado en pruebas sin especificarlo) — en ese
     * segundo caso ?? también caía a $branch->id, usando el id NUMÉRICO
     * PROPIO del modelo (ej. la transacción #14) como si fuera un branch id,
     * lo cual casi nunca coincide con la sucursal real del usuario y producía
     * un "Forbidden" para una solicitud legítima. Ahora se distingue
     * explícitamente por si el atributo existe (aunque sea null), no por su
     * valor: si existe y es null, no se restringe por sucursal (se deja que
     * hasBusinessAbility() valide solo por rol) en vez de comparar contra un
     * id que nunca fue pensado como branch id.
     */
    private function resolveBranchId(mixed $branch): ?int
    {
        if (is_numeric($branch)) {
            return (int) $branch;
        }

        if (! is_object($branch)) {
            return null;
        }

        if (method_exists($branch, 'getAttributes') && array_key_exists('branch_id', $branch->getAttributes())) {
            $value = $branch->branch_id;

            return $value === null ? null : (int) $value;
        }

        return isset($branch->id) ? (int) $branch->id : null;
    }
}
