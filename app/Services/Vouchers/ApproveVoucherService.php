<?php

declare(strict_types=1);

namespace App\Services\Vouchers;

use App\Enums\CustomerDistributorRelationshipStatus;
use App\Enums\CustomerStatus;
use App\Enums\VoucherRequestStatus;
use App\Enums\VoucherStatus;
use App\Mail\VoucherIssuedMail;
use App\Models\BranchSetting;
use App\Models\CustomerDistributor;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRequest;
use App\Services\Financial\FinancialCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class ApproveVoucherService
{
    public function __construct(
        private readonly FinancialCalculationService $financial,
    ) {}

    public function execute(User $user, VoucherRequest $voucherRequest): Voucher
    {
        $voucher = DB::transaction(function () use ($user, $voucherRequest): Voucher {
            if ($voucherRequest->status !== VoucherRequestStatus::PENDIENTE) {
                abort(422, 'La solicitud ya fue resuelta.');
            }

            /** @var VoucherRequest $voucherRequest */
            $voucherRequest->load(['distributor.person', 'customer.person']);

            $distributor = $voucherRequest->distributor;
            $snapshot = $voucherRequest->snapshot_json ?? [];
            $totalDebt = (float) ($snapshot['total_debt_amount'] ?? $voucherRequest->requested_amount);
            $availableCredit = (float) $distributor->available_credit;

            if ($availableCredit < $totalDebt) {
                abort(422, 'El crédito disponible de la distribuidora es insuficiente para aprobar el vale.');
            }

            $branchSetting = BranchSetting::query()
                ->firstOrCreate(['branch_id' => $distributor->branch_id])
                ->refresh();

            $reactivationPending = $distributor->prevale_required_after_credit_increase_at !== null;

            $preValeResult = $this->financial->validatePreVale(
                requestedAmount: (float) $voucherRequest->requested_amount,
                availableCredit: $availableCredit,
                totalCreditLimit: (float) $distributor->credit_limit,
                maxPercentage: (float) $branchSetting->pre_vale_max_percentage,
                toleranceAmount: (float) $branchSetting->pre_vale_tolerance_amount,
                reactivationPending: $reactivationPending,
            );

            if (! $preValeResult->allowed) {
                abort(422, $preValeResult->reason ?? 'El monto excede el máximo permitido para el primer vale.');
            }

            $distributor->decrement('available_credit', $totalDebt);

            if ($reactivationPending) {
                // La regla del 50% ya se aplicó a este vale (el primero desde el
                // aumento de línea); se libera para que los siguientes vuelvan a
                // comportarse como vale digital normal.
                $distributor->update(['prevale_required_after_credit_increase_at' => null]);
            }

            $voucherNumber = 'V-'.((int) Voucher::query()->max('id') + 1);

            $voucher = Voucher::query()->create([
                'voucher_number' => $voucherNumber,
                'distributor_id' => $distributor->id,
                'customer_id' => $voucherRequest->customer_id,
                'financial_product_id' => $voucherRequest->financial_product_id,
                'voucher_request_id' => $voucherRequest->id,
                'branch_id' => $distributor->branch_id,
                'created_by_user_id' => $voucherRequest->created_by_user_id,
                'approved_by_user_id' => $user->id,
                'status' => VoucherStatus::APROBADO,
                'is_pre_vale' => $voucherRequest->is_pre_vale,
                'amount' => $snapshot['principal'] ?? $voucherRequest->requested_amount,
                'company_commission_percentage_snapshot' => $snapshot['company_commission_percentage_snapshot'] ?? 0,
                'company_commission_amount' => $snapshot['company_commission_amount'] ?? 0,
                'insurance_amount_snapshot' => $snapshot['insurance_amount_snapshot'] ?? 0,
                'interest_percentage_snapshot' => $snapshot['interest_percentage_snapshot'] ?? 0,
                'interest_amount' => $snapshot['interest_amount'] ?? 0,
                'distributor_profit_percentage_snapshot' => $snapshot['distributor_profit_percentage_snapshot'] ?? 0,
                'distributor_profit_amount' => $snapshot['distributor_profit_amount'] ?? 0,
                'late_fee_amount_snapshot' => $snapshot['late_fee_amount_snapshot'] ?? 0,
                'total_debt_amount' => $totalDebt,
                'fortnightly_payment_amount' => $snapshot['fortnightly_payment_amount'] ?? 0,
                'total_fortnights' => $snapshot['total_fortnights'] ?? 0,
                'payments_made' => 0,
                'current_balance' => $totalDebt,
                'issued_at' => now(),
                'is_canceled' => false,
            ]);

            $voucherRequest->update([
                'status' => VoucherRequestStatus::APROBADO,
                'decided_by_user_id' => $user->id,
                'decided_at' => now(),
            ]);

            if ($voucherRequest->is_pre_vale) {
                CustomerDistributor::query()
                    ->where('distributor_id', $distributor->id)
                    ->where('customer_id', $voucherRequest->customer_id)
                    ->where('relationship_status', CustomerDistributorRelationshipStatus::ACTIVA->value)
                    ->update(['prevale_approved' => true]);

                // Un cliente sin verificar por la cajera pudo solicitar este vale
                // precisamente por ser un prevale (ver RequestVoucherService); al
                // aprobarse, el cliente queda activo sin esperar la verificacion manual.
                $customer = $voucherRequest->customer;
                if ($customer->status !== CustomerStatus::ACTIVO) {
                    $customer->update([
                        'status' => CustomerStatus::ACTIVO,
                        'verified_at' => $customer->verified_at ?? now(),
                        'verified_by_user_id' => $customer->verified_by_user_id ?? $user->id,
                    ]);
                }
            }

            return $voucher;
        });

        $this->sendIssuedMail($voucher);

        return $voucher;
    }

    /**
     * Se envia fuera de la transaccion: un fallo de SMTP no debe revertir la
     * aprobacion del vale (que ya quedo en firme en la BD), asi que solo se
     * registra el error si el correo no pudo mandarse.
     */
    private function sendIssuedMail(Voucher $voucher): void
    {
        $voucher->loadMissing(['customer.person', 'distributor.person', 'branch.setting']);

        $email = $voucher->customer?->person?->email;
        if ($email === null || $email === '') {
            return;
        }

        try {
            Mail::to($email)->send(new VoucherIssuedMail($voucher));
        } catch (Throwable $e) {
            Log::error('No se pudo enviar el correo de vale emitido.', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
