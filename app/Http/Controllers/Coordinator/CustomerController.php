<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\ApiController;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $customers = Customer::query()
            ->with(['person', 'branch', 'distributors.person'])
            ->when(! $user->hasGlobalBusinessRole(), fn ($query) => $query->whereIn('branch_id', $user->activeBusinessBranchIds()))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('distributor_id'), fn ($query) => $query->ofDistributor($request->integer('distributor_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('verified'), fn ($query) => $request->boolean('verified') ? $query->verified() : $query->whereNull('verified_at'))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(CustomerResource::collection($customers)->response()->getData(true));
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        return $this->success(new CustomerResource($customer->load(['person', 'branch', 'distributors.person'])));
    }
}