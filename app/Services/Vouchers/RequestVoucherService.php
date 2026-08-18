<?php

declare(strict_types=1);

namespace App\Services\Vouchers;

use App\Enums\CustomerDistributorRelationshipStatus;
use App\Enums\CustomerStatus;
use App\Enums\VoucherRequestStatus;
use App\Enums\VoucherStatus;
use App\Models\BranchSetting;
use App\Models\Customer;
use App\Models\CustomerDistributor;
use App\Models\Distributor;
use App\Models\FinancialProduct;
use App\Models\User;
use App\Models\VoucherRequest;
use App\Services\Financial\FinancialCalculationService;
use Illuminate\Support\Facades\DB;

final class RequestVoucherService
{
    public function __construct(
        private readonly FinancialCalculationService $financial,
    ) {
    }

    /**
     * @param  array{customer_id: int, financial_product_id: int}  $data
     */
    public function execute(User $user, array $data): VoucherRequest
    {
        return DB::transaction(function () use ($user, $data): VoucherRequest {
            $distributor = Distributor::query()
                ->where('person_id', $user->person_id)
                ->firstOrFail();

            $product = FinancialProduct::query()->findOrFail($data['financial_product_id']);
            $customer = Customer::query()->findOrFail($data['customer_id']);

            $this->assertCustomerBelongsToDistributor($distributor, $customer);
            $this->assertProductActive($product);

            $isFirstCustomerVoucher = ! $customer->vouchers()->exists();
            $requiresPreValeAfterIncrease = $distributor->prevale_required_after_credit_increase_at !== null;
            $requiresPreVale = $isFirstCustomerVoucher || $requiresPreValeAfterIncrease;

            if (! $requiresPreVale) {
                $this->assertCustomerVerified($customer);
            }

            $branchSetting = BranchSetting::query()
                ->firstOrCreate(['branch_id' => $distributor->branch_id])
                ->refresh();

            $categoryCommission = (float) ($distributor->category?->commission_percentage ?? 0);

            $snapshot = $this->financial->calculateVoucherSnapshot(
                principal: (float) $product->principal_amount,
                companyCommissionPercentage: (float) $product->company_commission_percentage,
                insuranceAmount: (float) $product->insurance_amount,
                fortnightlyInterestPercentage: (float) $product->fortnightly_interest_percentage,
                totalFortnights: $product->number_of_fortnights,
                categoryCommissionPercentage: $categoryCommission,
                lateFeeAmount: (float) $product->late_fee_amount,
            );

            if (! $this->financial->isMultipleOfStep((float) $product->principal_amount, (int) $branchSetting->voucher_amount_step)) {
                abort(422, 'El monto del producto no es múltiplo del paso configurado en la sucursal.');
            }

            $availableCredit = (float) $distributor->available_credit;
            if ($availableCredit < $snapshot->totalDebt) {
                abort(422, 'El crédito disponible de la distribuidora es insuficiente para cubrir la deuda total del vale.');
            }

            $preValeResult = $this->financial->validatePreVale(
                requestedAmount: (float) $product->principal_amount,
                availableCredit: $availableCredit,
                totalCreditLimit: (float) $distributor->credit_limit,
                maxPercentage: (float) $branchSetting->pre_vale_max_percentage,
                toleranceAmount: (float) $branchSetting->pre_vale_tolerance_amount,
                ruleRequired: $requiresPreVale,
            );

            if (! $preValeResult->allowed) {
                abort(422, $preValeResult->reason ?? 'El monto excede el máximo permitido para el primer vale.');
            }

            return VoucherRequest::query()->create([
                'distributor_id' => $distributor->id,
                'customer_id' => $customer->id,
                'financial_product_id' => $product->id,
                'branch_id' => $distributor->branch_id,
                'requested_amount' => $product->principal_amount,
                'is_pre_vale' => $preValeResult->ruleApplied,
                'status' => VoucherRequestStatus::PENDIENTE,
                'snapshot_json' => $snapshot->toArray(),
                'created_by_user_id' => $user->id,
            ]);
        });
    }

    private function assertCustomerBelongsToDistributor(Distributor $distributor, Customer $customer): void
    {
        $linked = CustomerDistributor::query()
            ->where('customer_id', $customer->id)
            ->where('distributor_id', $distributor->id)
            ->where('relationship_status', CustomerDistributorRelationshipStatus::ACTIVA->value)
            ->exists();

        if (! $linked) {
            abort(422, 'El cliente no pertenece a esta distribuidora.');
        }
    }

    private function assertCustomerVerified(Customer $customer): void
    {
        if ($customer->status !== CustomerStatus::ACTIVO || $customer->verified_at === null) {
            abort(422, 'El cliente debe estar activo y verificado por la cajera para solicitar un vale.');
        }
    }

    private function assertProductActive(FinancialProduct $product): void
    {
        if (! $product->is_active) {
            abort(422, 'El producto financiero seleccionado no está activo.');
        }
    }
}