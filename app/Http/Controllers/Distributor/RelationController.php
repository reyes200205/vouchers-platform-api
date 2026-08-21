<?php

declare(strict_types=1);

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\ApiController;
use App\Http\Resources\CutoffRelationResource;
use App\Models\CutoffRelation;
use App\Models\Distributor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Estado de cuenta de la distribuidora: sus propias relaciones de corte (lo
 * que le toca remitirle a la sucursal cada periodo -- referencia de pago,
 * quincenas incluidas, comisión que se queda, estado). La distribuidora
 * SIEMPRE se resuelve por el person_id del usuario autenticado, nunca por un
 * distributor_id que venga del request: así no hay forma de que una
 * distribuidora vea las relaciones (ni los datos de clientes) de otra.
 */
final class RelationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $distributor = Distributor::query()
            ->where('person_id', $user->person_id)
            ->firstOrFail();

        $relations = CutoffRelation::query()
            ->with(['items.customer.person'])
            ->where('distributor_id', $distributor->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('generated_at')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            CutoffRelationResource::collection($relations)->response()->getData(true)
        );
    }

    public function show(Request $request, CutoffRelation $cutoffRelation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $distributor = Distributor::query()
            ->where('person_id', $user->person_id)
            ->firstOrFail();

        // 404 en vez de 403: no le confirmamos a la distribuidora que la
        // relación existe (es de otra distribuidora), simplemente no la ve.
        if ($cutoffRelation->distributor_id !== $distributor->id) {
            abort(404);
        }

        $cutoffRelation->load(['items.customer.person']);

        return $this->success(new CutoffRelationResource($cutoffRelation));
    }
}
