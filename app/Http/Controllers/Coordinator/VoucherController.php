<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Vouchers\ApproveVoucherRequest;
use App\Http\Resources\VoucherRequestResource;
use App\Http\Resources\VoucherResource;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Vouchers\ApproveVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VoucherController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $branchIds = $user->activeBusinessBranchIds();

        $vouchers = Voucher::query()
            ->with(['customer.person'])
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            VoucherResource::collection($vouchers)->response()->getData(true)
        );
    }

    public function show(Voucher $voucher): JsonResponse
    {
        return $this->success(new VoucherResource($voucher->load(['customer.person', 'voucherRequest'])));
    }

    public function approve(ApproveVoucherRequest $request, VoucherRequest $voucherRequest, ApproveVoucherService $service, AuditLogger $audit): JsonResponse
    {
        $voucher = $service->execute($request->user(), $voucherRequest);

        $audit->record(
            $request,
            'VOUCHER_APPROVED',
            'vouchers',
            'Vale aprobado; credito disponible descontado.',
            $voucher->branch_id,
            ['voucher_id' => $voucher->id, 'voucher_number' => $voucher->voucher_number, 'total_debt' => $voucher->total_debt_amount]
        );

        return $this->success(new VoucherResource($voucher->load(['customer.person'])));
    }
}