<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Reconciliations\ManualMatchDepositRequest;
use App\Http\Requests\Reconciliations\RejectReconciliationRequest;
use App\Http\Requests\Reconciliations\VerifyReconciliationRequest;
use App\Http\Resources\ReconciliationResource;
use App\Models\BankTransaction;
use App\Models\Reconciliation;
use App\Services\Audit\AuditLogger;
use App\Services\Reconciliations\ManualMatchDepositService;
use App\Services\Reconciliations\RejectReconciliationService;
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

        return $this->created(new ReconciliationResource($reconciliation->load([
            'bankTransaction',
            'distributorPayment.distributor.person',
            'distributorPayment.distributor.category',
            'distributorPayment.cutoffRelation.cutoff.branch',
        ])));
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
                'is_retroactive_correction' => $reconciliation->is_retroactive_correction,
                'waived_late_fees_total' => $reconciliation->waived_late_fees_total,
            ]
        );

        return $this->success(new ReconciliationResource($reconciliation->load([
            'bankTransaction',
            'distributorPayment.distributor.person',
            'distributorPayment.distributor.category',
            'distributorPayment.cutoffRelation.cutoff.branch',
        ])));
    }

    public function reject(RejectReconciliationRequest $request, Reconciliation $reconciliation, RejectReconciliationService $service, AuditLogger $audit): JsonResponse
    {
        // Se capturan los datos antes de ejecutar el servicio porque este
        // elimina la conciliación y el pago detectado (ver
        // RejectReconciliationService): después de la llamada ya no hay de
        // dónde volver a leer cutoff_relation_id vía la relación.
        $reconciliationId = $reconciliation->id;
        $bankTransactionId = $reconciliation->bank_transaction_id;
        $cutoffRelationId = $reconciliation->distributorPayment?->cutoff_relation_id;
        $reconciledAmount = $reconciliation->reconciled_amount;
        $rejectionReason = $request->string('rejection_reason')->value();

        $service->execute($request->user(), $reconciliation);

        $audit->record(
            $request,
            'RECONCILIATION_REJECTED',
            'reconciliations',
            'Conciliación manual rechazada; la transacción bancaria vuelve a estar disponible.',
            null,
            [
                'reconciliation_id' => $reconciliationId,
                'bank_transaction_id' => $bankTransactionId,
                'cutoff_relation_id' => $cutoffRelationId,
                'amount' => $reconciledAmount,
                'rejection_reason' => $rejectionReason,
            ]
        );

        return $this->success(null, 'Conciliación rechazada; la transacción bancaria vuelve a estar disponible.');
    }
}