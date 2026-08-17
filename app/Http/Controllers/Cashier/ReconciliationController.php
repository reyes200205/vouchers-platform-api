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

        $reconciliations = \App\Models\Reconciliation::query()
            ->with('distributorPayment')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('pending_verification') && $request->boolean('pending_verification'), fn ($query) => $query->whereNull('verified_at'))
            ->when($user->isGeneralManager() === false, fn ($query) => $query->where('reconciled_by_user_id', $user->id))
            ->latest('reconciled_at')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            ReconciliationResource::collection($reconciliations)->response()->getData(true)
        );
    }
}