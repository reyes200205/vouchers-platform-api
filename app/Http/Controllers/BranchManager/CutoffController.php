<?php

declare(strict_types=1);

namespace App\Http\Controllers\BranchManager;

use App\Enums\AuditEventType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Cutoffs\GenerateCutoffRequest;
use App\Http\Resources\CutoffResource;
use App\Models\Branch;
use App\Models\Cutoff;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Cutoffs\GenerateCutoffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CutoffController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // activeBusinessBranchIds() no distingue de rol: antes se usaba
        // siempre para filtrar, así que un general_manager (rol global, sin
        // "su" sucursal -- ve todas) solo veía los cortes de la sucursal
        // donde quedó asignado su vínculo (la matriz), sin importar qué
        // sucursal seleccionara en el selector del frontend. Un rol global
        // ve TODAS las sucursales quando no pide una en concreto.
        $isGlobal = $user->hasGlobalBusinessRole();
        $branchIds = $user->activeBusinessBranchIds();
        $requestedBranchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;

        $cutoffs = Cutoff::query()
            ->withCount('relations')
            ->withSum('relations', 'total_amount_due')
            ->when(
                $requestedBranchId !== null,
                function ($query) use ($requestedBranchId, $isGlobal, $branchIds) {
                    // Un rol de sucursal no puede pedir una sucursal ajena --
                    // si pide una que no es suya, se ignora el branch_id (se
                    // comporta igual que si no lo hubiera mandado) en vez de
                    // fallar o filtrar por accidente una sucursal que no le
                    // pertenece.
                    if ($isGlobal || in_array($requestedBranchId, $branchIds, true)) {
                        $query->where('branch_id', $requestedBranchId);
                    } elseif ($branchIds !== []) {
                        $query->whereIn('branch_id', $branchIds);
                    }
                },
                function ($query) use ($isGlobal, $branchIds) {
                    if (! $isGlobal && $branchIds !== []) {
                        $query->whereIn('branch_id', $branchIds);
                    }
                }
            )
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('scheduled_at')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            CutoffResource::collection($cutoffs)->response()->getData(true)
        );
    }

    public function show(Request $request, Cutoff $cutoff): JsonResponse
    {
        // relations.items.voucher se carga para que CutoffRelationItemResource
        // pueda calcular commission_forfeited_amount (cuánta comisión hubiera
        // ganado la distribuidora si el item no hubiera llegado tarde) sin
        // disparar una consulta por item.
        $cutoff->load('relations.distributor.person', 'relations.items.customer.person', 'relations.items.voucher', 'relations.retroactiveReconciliation');

        return $this->success(new CutoffResource($cutoff));
    }

    public function generate(GenerateCutoffRequest $request, Branch $branch, GenerateCutoffService $service, AuditLogger $audit): JsonResponse
    {
        $cutoff = $service->execute($request->user(), $branch, $request->validated());

        $audit->record(
            $request,
            AuditEventType::Generated,
            'cutoffs',
            'Corte generado.',
            $branch->id,
            [
                'cutoff_id' => $cutoff->id,
                'period_start' => $request->validated('period_start'),
                'period_end' => $request->validated('period_end'),
            ]
        );

        return $this->created(new CutoffResource($cutoff->load('relations.distributor.person', 'relations.items.customer.person', 'relations.items.voucher', 'relations.retroactiveReconciliation')));
    }
}