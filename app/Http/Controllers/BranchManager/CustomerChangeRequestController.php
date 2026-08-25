<?php

declare(strict_types=1);

namespace App\Http\Controllers\BranchManager;

use App\Enums\AuditEventType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Customers\DecideCustomerChangeRequest;
use App\Http\Resources\CustomerChangeRequestResource;
use App\Models\CustomerChangeRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Customers\ApproveCustomerChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerChangeRequestController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $requests = CustomerChangeRequest::query()
            ->with(['customer.person'])
            ->when(! $user->hasGlobalBusinessRole(), function ($query) use ($user): void {
                $query->whereHas('customer', fn ($q) => $q->whereIn('branch_id', $user->activeBusinessBranchIds()));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(CustomerChangeRequestResource::collection($requests)->response()->getData(true));
    }

    public function decide(DecideCustomerChangeRequest $request, CustomerChangeRequest $customerChangeRequest, ApproveCustomerChangeService $service, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasBusinessAbility('customers.update.approve', $customerChangeRequest->customer->branch_id)) {
            return $this->forbidden();
        }

        $changeRequest = $service->execute($user, $customerChangeRequest, $request->validated());

        $audit->record(
            $request,
            AuditEventType::Resolved,
            'customers',
            'Solicitud de cambio de datos resuelta: ' . $changeRequest->status->value . '.',
            $changeRequest->customer->branch_id,
            [
                'customer_id' => $changeRequest->customer_id,
                'change_request_id' => $changeRequest->id,
                'change_type' => $changeRequest->change_type->value,
                'decision' => $changeRequest->status->value,
                'rejection_reason' => $changeRequest->rejection_reason,
                'new_values' => $changeRequest->new_values_json,
            ],
            null,
            $changeRequest->old_values_json
        );

        return $this->success(new CustomerChangeRequestResource($changeRequest->load('customer')));
    }
}