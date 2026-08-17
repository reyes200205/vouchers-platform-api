<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Reconciliations\ManualMatchDepositRequest;
use App\Http\Requests\Reconciliations\VerifyReconciliationRequest;
use App\Http\Resources\ReconciliationResource;
use App\Models\BankTransaction;
use App\Models\Reconciliation;
use App\Services\Audit\AuditLogger;
use App\Services\Reconciliations\ManualMatchDepositService;
use App\Services\Reconciliations\VerifyReconciliationService;
use Illuminate\Http\JsonResponse;

final class ReconciliationController extends ApiController
{
    public function manualMatch(ManualMatchDepositRequest $request, BankTransaction $bankTransaction, ManualMatchDepositService $service, AuditLogger $audit): JsonResponse
    {
        $reconciliation = $service->execute($request->user(), $bankTransaction, $request->validated());

        $audit->record(
            $request,
            'RECONCILIATION_MANUAL_MATCHED',
            'reconciliations',
            'Conciliación manual registrada, pendiente de segunda autorización.',
            null,
            [
                'reconciliation_id' => $reconciliation->id,
                'bank_transaction_id' => $bankTransaction->id,
                'cutoff_relation_id' => $reconciliation->distributor_payment_id ? \App\Models\DistributorPayment::query()->find($reconciliation->distributor_payment_id)?->cutoff_relation_id : null,
                'amount' => $reconciliation->reconciled_amount,
            ]
        );

        return $this->created(new ReconciliationResource($reconciliation->load('distributorPayment')));
    }

    public function verify(VerifyReconciliationRequest $request, Reconciliation $reconciliation, VerifyReconciliationService $service, AuditLogger $audit): JsonResponse
    {
        $reconciliation = $service->execute($request->user(), $reconciliation);

        $audit->record(
            $request,
            'RECONCILIATION_VERIFIED',
            'reconciliations',
            'Segunda autorización de conciliación completada.',
            null,
            [
                'reconciliation_id' => $reconciliation->id,
                'status' => $reconciliation->status->value,
                'verified_by_user_id' => $reconciliation->verified_by_user_id,
            ]
        );

        return $this->success(new ReconciliationResource($reconciliation->load('distributorPayment')));
    }
}