<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Vouchers\ApproveVoucherRequest;
use App\Http\Requests\Vouchers\RejectVoucherRequest;
use App\Http\Resources\VoucherRequestResource;
use App\Http\Resources\VoucherResource;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Vouchers\ApproveVoucherService;
use App\Services\Vouchers\RejectVoucherService;
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
            ->with(['customer.person', 'branch.setting'])
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

    public function reject(RejectVoucherRequest $request, VoucherRequest $voucherRequest, RejectVoucherService $service, AuditLogger $audit): JsonResponse
    {
        $voucherRequest = $service->execute($request->user(), $voucherRequest, $request->validated('reason'));

        $audit->record(
            $request,
            'VOUCHER_REJECTED',
            'vouchers',
            'Solicitud de vale rechazada; credito reservado devuelto a la distribuidora.',
            $voucherRequest->branch_id,
            ['voucher_request_id' => $voucherRequest->id, 'reason' => $voucherRequest->rejection_reason]
        );

        return $this->success(new VoucherRequestResource($voucherRequest->load(['customer.person', 'financialProduct'])));
    }
}