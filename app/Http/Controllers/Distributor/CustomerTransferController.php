<?php

declare(strict_types=1);

namespace App\Http\Controllers\Distributor;

use App\Enums\AuditEventType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Transfers\RespondCustomerTransferRequest;
use App\Http\Requests\Transfers\StoreCustomerTransferRequest;
use App\Http\Resources\CustomerTransferRequestResource;
use App\Models\Customer;
use App\Models\CustomerTransferRequest;
use App\Models\Distributor;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Transfers\CancelCustomerTransferService;
use App\Services\Transfers\DecideDestinationTransferService;
use App\Services\Transfers\FinalizeCustomerTransferService;
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
        $direction = $request->string('direction', 'incoming')->value();

        $requests = CustomerTransferRequest::query()
            ->with(['customer.person', 'sourceDistributor.person', 'destinationDistributor.person'])
            ->when(
                $direction === 'outgoing',
                fn ($query) => $query->where('source_distributor_id', $distributor->id),
                fn ($query) => $query->where('destination_distributor_id', $distributor->id),
            )
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(CustomerTransferRequestResource::collection($requests)->response()->getData(true));
    }

    public function directory(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $distributor = Distributor::query()->where('person_id', $user->person_id)->firstOrFail();

        $candidates = Distributor::query()
            ->with('person')
            ->where('id', '!=', $distributor->id)
            ->where('status', 'ACTIVA')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->value();
                $query->where(function ($q) use ($search): void {
                    $q->where('distributor_number', 'like', "%{$search}%")
                        ->orWhereHas('person', function ($personQuery) use ($search): void {
                            $personQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->limit(20)
            ->get()
            ->map(fn (Distributor $candidate) => [
                'id' => $candidate->id,
                'distributor_number' => $candidate->distributor_number,
                'name' => trim($candidate->person->first_name.' '.$candidate->person->last_name) ?: null,
                'branch_id' => $candidate->branch_id,
            ]);

        return $this->success($candidates);
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
            'Transferencia de cliente solicitada por distribuidora origen.',
            $customer->branch_id,
            [
                'customer_id' => $customer->id,
                'transfer_request_id' => $transferRequest->id,
                'destination_distributor_id' => $transferRequest->destination_distributor_id,
            ]
        );

        return $this->created(new CustomerTransferRequestResource($transferRequest->load(['customer.person', 'sourceDistributor.person', 'destinationDistributor.person'])));
    }

    public function respond(RespondCustomerTransferRequest $request, CustomerTransferRequest $customerTransferRequest, DecideDestinationTransferService $service, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasBusinessAbility('customers.transfer.respond')) {
            return $this->forbidden();
        }

        $transferRequest = $service->execute($user, $customerTransferRequest, $request->validated());

        $audit->record(
            $request,
            AuditEventType::Resolved,
            'customers',
            'Distribuidora destino respondió la transferencia: '.$transferRequest->status->value.'.',
            $transferRequest->customer->branch_id,
            [
                'transfer_request_id' => $transferRequest->id,
                'customer_id' => $transferRequest->customer_id,
                'decision' => $transferRequest->status->value,
            ]
        );

        return $this->success(new CustomerTransferRequestResource($transferRequest->load(['customer.person', 'sourceDistributor.person', 'destinationDistributor.person'])));
    }

    public function acceptClient(Request $request, CustomerTransferRequest $customerTransferRequest, FinalizeCustomerTransferService $service, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasBusinessAbility('customers.transfer.accept-client')) {
            return $this->forbidden();
        }

        $transferRequest = $service->execute($user, $customerTransferRequest);

        $audit->record(
            $request,
            AuditEventType::Resolved,
            'customers',
            'Distribuidora destino aceptó al cliente transferido.',
            $transferRequest->customer->branch_id,
            [
                'transfer_request_id' => $transferRequest->id,
                'customer_id' => $transferRequest->customer_id,
                'destination_distributor_id' => $transferRequest->destination_distributor_id,
            ]
        );

        return $this->success(new CustomerTransferRequestResource($transferRequest->load(['customer.person', 'sourceDistributor.person', 'destinationDistributor.person'])));
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
            ['transfer_request_id' => $transferRequest->id, 'customer_id' => $transferRequest->customer_id]
        );

        return $this->success(new CustomerTransferRequestResource($transferRequest->load('customer')));
    }
}
