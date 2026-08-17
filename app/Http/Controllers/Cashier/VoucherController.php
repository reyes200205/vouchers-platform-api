<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Vouchers\DisburseVoucherRequest;
use App\Http\Resources\VoucherResource;
use App\Models\Voucher;
use App\Services\Audit\AuditLogger;
use App\Services\Vouchers\DisburseVoucherService;
use Illuminate\Http\JsonResponse;

final class VoucherController extends ApiController
{
    public function disburse(DisburseVoucherRequest $request, Voucher $voucher, DisburseVoucherService $service, AuditLogger $audit): JsonResponse
    {
        $voucher = $service->execute($request->user(), $voucher, $request->validated());

        $audit->record(
            $request,
            'VOUCHER_DISBURSED',
            'vouchers',
            'Vale dispersado por cajera.',
            $voucher->branch_id,
            [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'transfer_reference' => $voucher->transfer_reference,
                'authorized_number' => $voucher->authorized_number,
            ]
        );

        return $this->success(new VoucherResource($voucher->load(['customer.person'])));
    }
}