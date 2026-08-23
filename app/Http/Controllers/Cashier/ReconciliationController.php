<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Reconciliations\ImportBankDepositsRequest;
use App\Http\Resources\BankTransactionResource;
use App\Http\Resources\ReconciliationResource;
use App\Models\BankTransaction;
use App\Models\Branch;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Reconciliations\AutoMatchDepositsService;
use App\Services\Reconciliations\ImportBankDepositsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReconciliationController extends ApiController
{
    public function import(ImportBankDepositsRequest $request, Branch $branch, ImportBankDepositsService $service, AutoMatchDepositsService $autoMatch, AuditLogger $audit): JsonResponse
    {
        $import = $service->execute($request->user(), $branch, $request->validated());
        $matched = $autoMatch->execute($request->user());

        $audit->record(
            $request,
            'BANK_IMPORT_COMPLETED',
            'reconciliations',
            'Archivo bancario importado.',
            $branch->id,
            [
                'bank_transaction_import_id' => $import->id,
                'filename' => $import->filename,
                'row_count' => $import->row_count,
                'error_count' => $import->error_count,
                'auto_matched' => $matched,
            ]
        );

        return $this->created([
            'import' => $import->only(['id', 'filename', 'row_count', 'error_count', 'errors_json', 'status']),
            'auto_matched' => $matched,
        ]);
    }

    public function bankTransactions(Request $request): JsonResponse
    {
        $transactions = BankTransaction::query()
            ->with('reconciliation')
            ->when($request->filled('reconciled'), fn ($query) => $request->boolean('reconciled')
                ? $query->whereHas('reconciliation')
                : $query->whereDoesntHave('reconciliation'))
            ->when($request->filled('reference'), fn ($query) => $query->where('reference', 'like', '%' . $request->string('reference')->value() . '%'))
            ->latest('transaction_date')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            BankTransactionResource::collection($transactions)->response()->getData(true)
        );
    }

    public function reconciliations(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $isGlobal = $user->hasGlobalBusinessRole();
        // reconciliations.verify (branch_manager/general_manager, ver
        // config/business-authorization.php) es quien aprueba/rechaza -- por
        // eso necesita ver TODAS las solicitudes pendientes de su sucursal,
        // no solo las que el mismo generó. Antes esta vista filtraba por
        // reconciled_by_user_id para cualquiera que no fuera general_manager,
        // así que un gerente de sucursal nunca veía las solicitudes que
        // mandaba la cajera -- la bandeja de conciliaciones pendientes le
        // salía siempre vacía aunque sí hubiera algo que aprobar.
        $canApprove = $user->hasBusinessAbility('reconciliations.verify');
        $branchIds = $isGlobal ? [] : $user->activeBusinessBranchIds();

        $reconciliations = \App\Models\Reconciliation::query()
            ->with([
                'bankTransaction',
                'distributorPayment.distributor.person',
                'distributorPayment.distributor.category',
                'distributorPayment.cutoffRelation.cutoff.branch',
            ])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('pending_verification') && $request->boolean('pending_verification'), fn ($query) => $query->whereNull('verified_at'))
            ->when(! $isGlobal, function ($query) use ($canApprove, $branchIds, $user) {
                if ($canApprove) {
                    $query->whereHas(
                        'distributorPayment.cutoffRelation.cutoff',
                        fn ($q) => $q->whereIn('branch_id', $branchIds)
                    );
                } else {
                    // Quien no puede aprobar (ej. cajera) solo ve lo que ella
                    // misma generó, para dar seguimiento a sus propias
                    // solicitudes -- no toda la bandeja de la sucursal.
                    $query->where('reconciled_by_user_id', $user->id);
                }
            })
            ->latest('reconciled_at')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            ReconciliationResource::collection($reconciliations)->response()->getData(true)
        );
    }
}