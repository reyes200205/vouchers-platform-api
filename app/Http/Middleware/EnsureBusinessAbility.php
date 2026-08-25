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
        $branchId = $this->resolveBranchId($branch, $branchParameter);

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
     * $branch->branch_id ?? $branch->id no distinguía entre los casos
     * distintos que se veían igual (todos "sin valor" bajo ??):
     *
     *   1. El modelo enlazado por ruta ES la sucursal misma (ej. Branch en
     *      `business.ability:branches.manage,branch`) -- ahí sí corresponde
     *      usar su propio id como branch id.
     *   2. El modelo SÍ tiene columna branch_id pero está en null (ej. un
     *      BankTransaction importado antes de que ImportBankDepositsService
     *      empezara a guardarlo) -- no hay sucursal que validar todavía.
     *   3. El modelo no tiene columna branch_id propia pero SÍ puede
     *      resolver la sucursal a la que pertenece a través de sus
     *      relaciones (ej. Reconciliation -> distributorPayment ->
     *      cutoffRelation -> cutoff) -- expone `resolveBusinessBranchId()`
     *      para eso.
     *   4. El modelo no tiene ningún concepto de sucursal propio ni forma de
     *      resolverlo -- nunca debería restringirse por sucursal vía este
     *      parámetro.
     *
     * Antes, tanto el caso 2 como el 4 caían al mismo `?? $branch->id`,
     * usando el id NUMÉRICO PROPIO del modelo (ej. la reconciliación #14)
     * como si fuera un branch id -- eso casi nunca coincide con la sucursal
     * real del usuario y producía un "Forbidden" para una solicitud
     * legítima (pasó primero con BankTransaction en /manual-match, y luego
     * con Reconciliation en /verify). Una primera corrección hizo que el
     * caso 3 (Reconciliation) cayera en "no restringir" igual que el 4,
     * lo cual arregló el Forbidden indebido pero abrió un hueco real: un
     * gerente de sucursal podía verificar/rechazar conciliaciones de
     * CUALQUIER sucursal, no solo la suya (detectado por el test
     * "lets a branch manager verify pending reconciliations of their branch
     * only" en CashierFlowTest.php). Por eso ahora el caso 3 resuelve la
     * sucursal real vía `resolveBusinessBranchId()` en vez de renunciar a
     * validarla.
     */
    private function resolveBranchId(mixed $branch, ?string $parameterName = null): ?int
    {
        if (is_numeric($branch)) {
            // Si el parámetro enlazado es exactamente "branch", el ID numérico es el de la sucursal.
            if ($parameterName === 'branch') {
                return (int) $branch;
            }
            
            // Si no, necesitamos instanciar el modelo para saber su branch_id
            if ($parameterName === 'customer') {
                $model = \App\Models\Customer::find($branch);
                return $model ? (int) $model->branch_id : null;
            }
            if ($parameterName === 'customerChangeRequest') {
                $model = \App\Models\CustomerChangeRequest::find($branch);
                return $model ? (int) $model->customer?->branch_id : null;
            }
            if ($parameterName === 'customerPayment') {
                $model = \App\Models\CustomerPayment::find($branch);
                return $model ? (int) $model->customer?->branch_id : null;
            }
            if ($parameterName === 'customerTransferRequest') {
                $model = \App\Models\CustomerTransferRequest::find($branch);
                return $model ? (int) $model->customer?->branch_id : null;
            }
            // Fallback por defecto si no podemos resolver (no debería ocurrir si SubstituteBindings corre a tiempo)
            return null;
        }

        if (! is_object($branch)) {
            return null;
        }

        if ($branch instanceof \App\Models\Branch) {
            return (int) $branch->id;
        }

        if (method_exists($branch, 'resolveBusinessBranchId')) {
            $value = $branch->resolveBusinessBranchId();

            return $value === null ? null : (int) $value;
        }

        if (method_exists($branch, 'getAttributes') && array_key_exists('branch_id', $branch->getAttributes())) {
            $value = $branch->branch_id;

            return $value === null ? null : (int) $value;
        }

        return null;
    }
}
