<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\ApiController;
use App\Http\Resources\CustomerPaymentResource;
use App\Models\CustomerPayment;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $branchIds = $user->activeBusinessBranchIds();

        $payments = CustomerPayment::query()
            ->with(['voucher', 'customer'])
            ->when($branchIds !== [], fn ($query) => $query->whereIn('distributor_id', function ($sub) use ($branchIds) {
                $sub->select('id')->from('distributors')->whereIn('branch_id', $branchIds);
            }))
            ->when($request->filled('voucher_id'), fn ($query) => $query->where('voucher_id', $request->integer('voucher_id')))
            ->when($request->filled('reversed'), fn ($query) => $query->where(fn ($q) => $request->boolean('reversed') ? $q->whereNotNull('reversed_at') : $q->whereNull('reversed_at')))
            ->latest('payment_date')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            CustomerPaymentResource::collection($payments)->response()->getData(true)
        );
    }

    public function voucherPayments(Request $request, Voucher $voucher): JsonResponse
    {
        $payments = $voucher->payments()
            ->with('collectedBy')
            ->latest('payment_date')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            CustomerPaymentResource::collection($payments)->response()->getData(true)
        );
    }
}