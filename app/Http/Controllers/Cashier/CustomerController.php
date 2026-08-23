<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashier;

use App\Enums\AuditEventType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Customers\StoreCustomerChangeRequest;
use App\Http\Requests\Customers\VerifyCustomerRequest;
use App\Http\Resources\CustomerChangeRequestResource;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Customers\RequestCustomerChangeService;
use App\Services\Customers\VerifyCustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerController extends ApiController
{
    public function verify(VerifyCustomerRequest $request, Customer $customer, VerifyCustomerService $service, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasBusinessAbility('customers.verify', $customer->branch_id)) {
            return $this->forbidden();
        }

        $customer = $service->execute($user, $customer, $request->validated());

        $audit->record(
            $request,
            AuditEventType::Verified,
            'customers',
            'Cliente verificado por cajera.',
            $customer->branch_id,
            ['customer_id' => $customer->id, 'customer_code' => $customer->customer_code]
        );

        return $this->success(new CustomerResource($customer->load(['person', 'branch', 'distributors.person'])));
    }

    public function storeChangeRequest(StoreCustomerChangeRequest $request, Customer $customer, RequestCustomerChangeService $service, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasBusinessAbility('customers.update.request', $customer->branch_id)) {
            return $this->forbidden();
        }

        $changeRequest = $service->execute($user, $customer, $request->validated());

        $audit->record(
            $request,
            AuditEventType::Requested,
            'customers',
            'Solicitud de cambio de datos del cliente registrada.',
            $customer->branch_id,
            ['customer_id' => $customer->id, 'change_request_id' => $changeRequest->id, 'change_type' => $changeRequest->change_type->value]
        );

        return $this->created(new CustomerChangeRequestResource($changeRequest));
    }
}