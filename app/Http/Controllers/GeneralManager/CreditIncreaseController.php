<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

use App\Enums\AuditEventType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Credit\DecideCreditIncreaseRequest;
use App\Http\Resources\CreditIncreaseRequestResource;
use App\Models\CreditIncreaseRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Credit\DecideCreditIncreaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreditIncreaseController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $branchIds = $user->activeBusinessBranchIds();

        $requests = CreditIncreaseRequest::query()
            ->with('distributor')
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            CreditIncreaseRequestResource::collection($requests)->response()->getData(true)
        );
    }

    public function decide(DecideCreditIncreaseRequest $request, CreditIncreaseRequest $creditIncreaseRequest, DecideCreditIncreaseService $service, AuditLogger $audit): JsonResponse
    {
        $creditIncreaseRequest->loadMissing('distributor');
        $oldCreditLimit = $creditIncreaseRequest->distributor->credit_limit;
        $oldAvailableCredit = $creditIncreaseRequest->distributor->available_credit;

        $creditIncreaseRequest = $service->execute($request->user(), $creditIncreaseRequest, $request->validated());

        $audit->record(
            $request,
            AuditEventType::Decided,
            'credit',
            'Aumento de línea resuelto por el gerente.',
            $creditIncreaseRequest->branch_id,
            [
                'credit_increase_request_id' => $creditIncreaseRequest->id,
                'distributor_id' => $creditIncreaseRequest->distributor_id,
                'status' => $creditIncreaseRequest->status->value,
                'approved_amount' => $creditIncreaseRequest->approved_amount,
                'decision_notes' => $creditIncreaseRequest->decision_notes,
                'credit_limit' => $creditIncreaseRequest->distributor->credit_limit,
                'available_credit' => $creditIncreaseRequest->distributor->available_credit,
            ],
            null,
            [
                'credit_limit' => $oldCreditLimit,
                'available_credit' => $oldAvailableCredit,
            ]
        );

        return $this->success(new CreditIncreaseRequestResource($creditIncreaseRequest->load('distributor')));
    }
}