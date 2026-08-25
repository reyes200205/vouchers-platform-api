<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Enums\AuditEventType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Credit\PreAuthorizeCreditIncreaseRequest;
use App\Http\Requests\Credit\StoreCreditIncreaseRequest;
use App\Http\Resources\CreditIncreaseRequestResource;
use App\Models\CreditIncreaseRequest;
use App\Models\Distributor;
use App\Services\Audit\AuditLogger;
use App\Services\Credit\PreAuthorizeCreditIncreaseService;
use App\Services\Credit\RequestCreditIncreaseService;
use Illuminate\Http\JsonResponse;

final class CreditIncreaseController extends ApiController
{
    public function store(StoreCreditIncreaseRequest $request, RequestCreditIncreaseService $service, AuditLogger $audit): JsonResponse
    {
        $distributor = Distributor::query()->findOrFail($request->validated('distributor_id'));
        $creditRequest = $service->execute($request->user(), $distributor, $request->validated());

        $audit->record(
            $request,
            AuditEventType::Requested,
            'credit',
            'Solicitud de aumento de línea creada.',
            $distributor->branch_id,
            [
                'credit_increase_request_id' => $creditRequest->id,
                'distributor_id' => $distributor->id,
                'requested_amount' => $creditRequest->requested_amount,
                'current_credit_limit' => $distributor->credit_limit,
                'current_available_credit' => $distributor->available_credit,
            ]
        );

        return $this->created(new CreditIncreaseRequestResource($creditRequest->load('distributor')));
    }

    public function preAuthorize(PreAuthorizeCreditIncreaseRequest $request, CreditIncreaseRequest $creditIncreaseRequest, PreAuthorizeCreditIncreaseService $service, AuditLogger $audit): JsonResponse
    {
        $requestedAmount = $creditIncreaseRequest->requested_amount;

        $creditIncreaseRequest = $service->execute($request->user(), $creditIncreaseRequest, $request->validated());

        $audit->record(
            $request,
            AuditEventType::PreAuthorized,
            'credit',
            'Aumento de línea pre-autorizado por el coordinador.',
            $creditIncreaseRequest->branch_id,
            [
                'credit_increase_request_id' => $creditIncreaseRequest->id,
                'distributor_id' => $creditIncreaseRequest->distributor_id,
                'requested_amount' => $requestedAmount,
                'pre_authorized_amount' => $creditIncreaseRequest->pre_authorized_amount,
            ]
        );

        return $this->success(new CreditIncreaseRequestResource($creditIncreaseRequest->load('distributor')));
    }
}