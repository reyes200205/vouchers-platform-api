<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Payments\ReverseCustomerPaymentRequest;
use App\Http\Requests\Payments\StoreCustomerPaymentRequest;
use App\Http\Resources\CustomerPaymentResource;
use App\Models\CustomerPayment;
use App\Models\Voucher;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\RecordCustomerPaymentService;
use App\Services\Payments\ReverseCustomerPaymentService;
use Illuminate\Http\JsonResponse;

final class PaymentController extends ApiController
{
    public function store(StoreCustomerPaymentRequest $request, RecordCustomerPaymentService $service, AuditLogger $audit): JsonResponse
    {
        $voucher = Voucher::query()->findOrFail($request->validated('voucher_id'));
        $payment = $service->execute($request->user(), $voucher, $request->validated());

        $audit->record(
            $request,
            'CUSTOMER_PAYMENT_RECORDED',
            'payments',
            'Pago de cliente registrado.',
            $voucher->branch_id,
            [
                'customer_payment_id' => $payment->id,
                'voucher_id' => $payment->voucher_id,
                'amount' => $payment->amount,
                'voucher_status' => $voucher->refresh()->status->value,
            ]
        );

        return $this->created(new CustomerPaymentResource($payment->load('voucher')));
    }

    public function reverse(ReverseCustomerPaymentRequest $request, CustomerPayment $customerPayment, ReverseCustomerPaymentService $service, AuditLogger $audit): JsonResponse
    {
        $payment = $service->execute($request->user(), $customerPayment, $request->validated());

        $audit->record(
            $request,
            'CUSTOMER_PAYMENT_REVERSED',
            'payments',
            'Pago de cliente reversado.',
            $payment->voucher->branch_id,
            [
                'customer_payment_id' => $payment->id,
                'voucher_id' => $payment->voucher_id,
                'amount' => $payment->amount,
                'reason' => $payment->reversal_reason,
            ]
        );

        return $this->success(new CustomerPaymentResource($payment->load('voucher')));
    }
}