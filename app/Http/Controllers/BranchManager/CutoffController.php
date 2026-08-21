<?php

declare(strict_types=1);

namespace App\Http\Controllers\BranchManager;

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
        $branchIds = $user->activeBusinessBranchIds();

        $cutoffs = Cutoff::query()
            ->withCount('relations')
            ->withSum('relations', 'total_amount_due')
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
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
        $cutoff->load('relations.distributor.person', 'relations.items.customer.person');

        return $this->success(new CutoffResource($cutoff));
    }

    public function generate(GenerateCutoffRequest $request, Branch $branch, GenerateCutoffService $service, AuditLogger $audit): JsonResponse
    {
        $cutoff = $service->execute($request->user(), $branch, $request->validated());

        $audit->record(
            $request,
            'CUTOFF_GENERATED',
            'cutoffs',
            'Corte generado.',
            $branch->id,
            [
                'cutoff_id' => $cutoff->id,
                'period_start' => $request->validated('period_start'),
                'period_end' => $request->validated('period_end'),
            ]
        );

        return $this->created(new CutoffResource($cutoff->load('relations.distributor.person', 'relations.items.customer.person')));
    }
}