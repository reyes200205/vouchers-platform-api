<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Enums\AuditEventType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Transfers\DecideCustomerTransferRequest;
use App\Http\Resources\CustomerTransferRequestResource;
use App\Models\CustomerTransferRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Transfers\DecideCustomerTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerTransferController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $requests = CustomerTransferRequest::query()
            ->with(['customer.person', 'sourceDistributor.person', 'destinationDistributor.person'])
            ->when(! $user->hasGlobalBusinessRole(), function ($query) use ($user): void {
                $query->whereHas('sourceDistributor', fn ($q) => $q->whereIn('branch_id', $user->activeBusinessBranchIds()));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(CustomerTransferRequestResource::collection($requests));
    }

    public function decide(DecideCustomerTransferRequest $request, CustomerTransferRequest $customerTransferRequest, DecideCustomerTransferService $service, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $sourceDistributor = $customerTransferRequest->sourceDistributor;

        if (! $user->hasBusinessAbility('customers.transfer.decide', $sourceDistributor->branch_id)) {
            return $this->forbidden();
        }

        $transferRequest = $service->execute($user, $customerTransferRequest, $request->validated());

        $audit->record(
            $request,
            AuditEventType::Resolved,
            'customers',
            'Solicitud de transferencia resuelta: ' . $transferRequest->status->value . '.',
            $sourceDistributor->branch_id,
            [
                'transfer_request_id' => $transferRequest->id,
                'customer_id' => $transferRequest->customer_id,
                'source_distributor_id' => $transferRequest->source_distributor_id,
                'destination_distributor_id' => $transferRequest->destination_distributor_id,
                'decision' => $transferRequest->status->value,
            ]
        );

        return $this->success(new CustomerTransferRequestResource($transferRequest->load(['customer.person', 'sourceDistributor.person', 'destinationDistributor.person'])));
    }
}