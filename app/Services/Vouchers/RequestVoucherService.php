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
use App\Models\Voucher;
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
            $this->assertNoActiveVoucher($customer);
            $this->assertProductActive($product);

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

            // El prevale es exclusivo del primer vale del cliente (nunca antes se le
            // aprobo uno, sin importar el estado actual de ese vale previo).
            $isNewCustomer = ! Voucher::query()->where('customer_id', $customer->id)->exists();

            $preValeResult = $this->financial->validatePreVale(
                requestedAmount: (float) $product->principal_amount,
                isNewCustomer: $isNewCustomer,
                availableCredit: $availableCredit,
                totalCreditLimit: (float) $distributor->credit_limit,
                maxPercentage: (float) $branchSetting->pre_vale_max_percentage,
                toleranceAmount: (float) $branchSetting->pre_vale_tolerance_amount,
            );

            if (! $preValeResult->allowed) {
                abort(422, $preValeResult->reason ?? 'El monto excede el máximo permitido para el primer vale.');
            }

            // Un cliente sin verificar por la cajera solo puede recibir una solicitud
            // de vale si esta califica como prevale (es su primer vale). Al aprobarse
            // ese prevale, el cliente pasa a ACTIVO automaticamente (ver
            // ApproveVoucherService). Fuera de ese caso, se exige la verificacion normal.
            $this->assertCustomerVerified($customer, $preValeResult->ruleApplied);

            // El credito se reserva desde que se pide el vale (no hasta que se aprueba),
            // para que la distribuidora no pueda comprometer mas credito del que tiene
            // mientras hay solicitudes pendientes. Si la cajera rechaza la solicitud,
            // RejectVoucherService devuelve este monto (ver tambien ApproveVoucherService,
            // que ya no descuenta de nuevo al aprobar).
            $distributor->decrement('available_credit', $snapshot->totalDebt);

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

    private function assertCustomerVerified(Customer $customer, bool $isPreVale): void
    {
        if ($customer->status === CustomerStatus::ACTIVO && $customer->verified_at !== null) {
            return;
        }

        if ($isPreVale && $customer->status === CustomerStatus::EN_VERIFICACION) {
            return;
        }

        abort(422, 'El cliente debe estar activo y verificado por la cajera para solicitar un vale.');
    }

    private function assertNoActiveVoucher(Customer $customer): void
    {
        $hasPendingRequest = VoucherRequest::query()
            ->where('customer_id', $customer->id)
            ->where('status', VoucherRequestStatus::PENDIENTE)
            ->exists();

        if ($hasPendingRequest) {
            abort(422, 'El cliente ya tiene una solicitud de vale pendiente de aprobación.');
        }

        $hasActiveVoucher = $customer->vouchers()
            ->whereIn('status', [
                VoucherStatus::APROBADO->value,
                VoucherStatus::ACTIVO->value,
                VoucherStatus::PAGO_PARCIAL->value,
                VoucherStatus::MOROSO->value,
            ])
            ->where('current_balance', '>', 0)
            ->exists();

        if ($hasActiveVoucher) {
            abort(422, 'El cliente ya tiene un vale activo con saldo pendiente.');
        }
    }

    private function assertProductActive(FinancialProduct $product): void
    {
        if (! $product->is_active) {
            abort(422, 'El producto financiero seleccionado no está activo.');
        }
    }
}