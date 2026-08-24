<?php

declare(strict_types=1);

namespace App\Http\Controllers\Distributor;

use App\Enums\AuditEventType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Transfers\StoreCustomerTransferRequest;
use App\Http\Resources\CustomerTransferRequestResource;
use App\Models\Customer;
use App\Models\CustomerTransferRequest;
use App\Models\Distributor;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Transfers\CancelCustomerTransferService;
use App\Services\Transfers\RequestCustomerTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerTransferController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $distributor = Distributor::query()->where('person_id', $user->person_id)->firstOrFail();

        $requests = CustomerTransferRequest::query()
            ->with(['customer.person', 'sourceDistributor.person', 'destinationDistributor.person'])
            ->where('destination_distributor_id', $distributor->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(CustomerTransferRequestResource::collection($requests));
    }

    public function store(StoreCustomerTransferRequest $request, Customer $customer, RequestCustomerTransferService $service, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasBusinessAbility('customers.transfer.request')) {
            return $this->forbidden();
        }

        $transferRequest = $service->execute($user, $customer, $request->validated());

        $audit->record(
            $request,
            AuditEventType::Requested,
            'customers',
            'Transferencia de cliente solicitada por distribuidora destino.',
            $customer->branch_id,
            [
                'customer_id' => $customer->id,
                'transfer_request_id' => $transferRequest->id,
                'source_distributor_id' => $transferRequest->source_distributor_id,
                'destination_distributor_id' => $transferRequest->destination_distributor_id,
            ]
        );

        return $this->created(new CustomerTransferRequestResource($transferRequest->load(['customer.person', 'sourceDistributor.person', 'destinationDistributor.person'])));
    }

    public function cancel(Request $request, CustomerTransferRequest $customerTransferRequest, CancelCustomerTransferService $service, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasBusinessAbility('customers.transfer.cancel')) {
            return $this->forbidden();
        }

        $transferRequest = $service->execute($user, $customerTransferRequest);

        $audit->record(
            $request,
            AuditEventType::Canceled,
            'customers',
            'Solicitud de transferencia de cliente cancelada.',
            $transferRequest->customer->branch_id,
            [
                'transfer_request_id' => $transferRequest->id,
                'customer_id' => $transferRequest->customer_id,
                'source_distributor_id' => $transferRequest->source_distributor_id,
                'destination_distributor_id' => $transferRequest->destination_distributor_id,
            ]
        );

        return $this->success(new CustomerTransferRequestResource($transferRequest->load('customer')));
    }
}