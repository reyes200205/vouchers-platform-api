<?php

declare(strict_types=1);

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Distributor;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Customers\StoreCustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $distributor = Distributor::query()
            ->where('person_id', $user->person_id)
            ->firstOrFail();

        $customers = Customer::query()
            ->with(['person', 'branch', 'distributors.person'])
            ->ofDistributor($distributor->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(CustomerResource::collection($customers));
    }

    public function store(StoreCustomerRequest $request, StoreCustomerService $service, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasBusinessAbility('customers.create')) {
            return $this->forbidden();
        }

        $customer = $service->execute($user, $request->validated());

        $audit->record(
            $request,
            'CUSTOMER_CREATED',
            'customers',
            'Cliente dado de alta por distribuidora, pendiente de verificacion.',
            $customer->branch_id,
            ['customer_id' => $customer->id, 'customer_code' => $customer->customer_code]
        );

        return $this->created(new CustomerResource($customer->load(['person', 'branch', 'distributors.person'])));
    }
}