<?php

declare(strict_types=1);

namespace App\Services\Vouchers;

use App\Enums\CustomerDistributorRelationshipStatus;
use App\Enums\CustomerStatus;
use App\Enums\VoucherRequestStatus;
use App\Enums\VoucherStatus;
use App\Mail\VoucherIssuedMail;
use App\Models\BranchSetting;
use App\Models\Customer;
use App\Models\CustomerDistributor;
use App\Models\Distributor;
use App\Models\FinancialProduct;
use App\Models\User;
use App\Models\VoucherRequest;
use App\Services\Financial\FinancialCalculationService;
use App\Services\Financial\PreValeValidationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class RequestVoucherService
{
    public function __construct(
        private readonly FinancialCalculationService $financial,
    ) {}

    /**
     * @param  array{customer_id: int, financial_product_id: int}  $data
     */
    public function execute(User $user, array $data): VoucherRequest
    {
        $voucherRequest = DB::transaction(function () use ($user, $data): VoucherRequest {
            $distributor = Distributor::query()
                ->where('person_id', $user->person_id)
                ->firstOrFail();

            $product = FinancialProduct::query()->findOrFail($data['financial_product_id']);
            $customer = Customer::query()->findOrFail($data['customer_id']);

            $customerDistributor = $this->assertCustomerBelongsToDistributor($distributor, $customer);
            $this->assertCustomerEligible($customer);
            $this->assertNoActiveVoucher($customer);
            $this->assertProductActive($product);
            $this->assertProductMatchesCategory($distributor, $product);

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
                // La multa por atraso vive en el producto (no todos los vales de la
                // sucursal tienen la misma multa); branch_settings solo aporta el
                // default con el que se resolvió el producto al crearlo.
                lateFeeAmount: (float) $product->late_fee_amount,
            );

            if (! $this->financial->isMultipleOfStep((float) $product->principal_amount, (int) $branchSetting->voucher_amount_step)) {
                abort(422, 'El monto del producto no es múltiplo del paso configurado en la sucursal.');
            }

            // El crédito disponible mide cuánto CAPITAL (principal) puede tener
            // prestado la distribuidora a la vez -- no el total a cobrar al
            // cliente, que ya trae intereses, seguro y comisión encima. Por eso
            // se compara contra el principal del producto, no contra totalDebt.
            $availableCredit = (float) $distributor->available_credit;
            if ($availableCredit < (float) $product->principal_amount) {
                abort(422, 'El crédito disponible de la distribuidora es insuficiente para cubrir el monto del vale.');
            }

            // Un cliente que ya viene con historial (prevale_approved en su
            // vinculo con esta distribuidora -- p.ej. porque ya tuvo un vale
            // aprobado antes, o porque llego por una transferencia desde otra
            // distribuidora, ver FinalizeCustomerTransferService) no debe
            // topar su primer vale con esta distribuidora al 50% del credito
            // disponible: esa regla es para proteger a la distribuidora de su
            // propio primer vale sin historial, no para castigar dos veces a
            // un cliente ya conocido.
            $preValeResult = $customerDistributor->prevale_approved
                ? PreValeValidationResult::allowed()
                : $this->financial->validatePreVale(
                    requestedAmount: (float) $product->principal_amount,
                    availableCredit: $availableCredit,
                    totalCreditLimit: (float) $distributor->credit_limit,
                    maxPercentage: (float) $branchSetting->pre_vale_max_percentage,
                    toleranceAmount: (float) $branchSetting->pre_vale_tolerance_amount,
                    reactivationPending: $distributor->prevale_required_after_credit_increase_at !== null,
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

        $this->sendIssuedMail($voucherRequest);

        return $voucherRequest;
    }

    /**
     * El cliente necesita esta informacion para poder presentarse con la
     * cajera a "ferear" el vale, asi que se manda apenas la distribuidora lo
     * pide -- no se espera a que la cajera lo apruebe (ver
     * ApproveVoucherService, que ya NO manda correo). Se envia fuera de la
     * transaccion y con su propio try/catch: un fallo de SMTP no debe
     * revertir la solicitud, que ya quedo en firme en la BD.
     */
    private function sendIssuedMail(VoucherRequest $voucherRequest): void
    {
        $voucherRequest->loadMissing(['customer.person', 'distributor.person', 'branch.setting', 'financialProduct']);

        $email = $voucherRequest->customer?->person?->email;
        if ($email === null || $email === '') {
            return;
        }

        $branchSetting = $voucherRequest->branch?->setting;
        $expirationDate = $branchSetting?->voucher_expiration_days !== null
            ? $voucherRequest->created_at->copy()->addDays((int) $branchSetting->voucher_expiration_days)
            : null;

        $person = $voucherRequest->customer?->person;
        $distributorPerson = $voucherRequest->distributor?->person;

        try {
            Mail::to($email)->send(new VoucherIssuedMail(
                customerName: trim(($person?->first_name ?? '').' '.($person?->last_name ?? '')) ?: 'Cliente',
                distributorName: trim(($distributorPerson?->first_name ?? '').' '.($distributorPerson?->last_name ?? '')) ?: 'Tu distribuidora',
                voucherNumber: $voucherRequest->financialProduct?->code ?? 'V-'.$voucherRequest->id,
                issuedAt: $voucherRequest->created_at,
                expirationDate: $expirationDate,
                amount: (float) $voucherRequest->requested_amount,
            ));
        } catch (Throwable $e) {
            Log::error('No se pudo enviar el correo de vale emitido.', [
                'voucher_request_id' => $voucherRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function assertCustomerBelongsToDistributor(Distributor $distributor, Customer $customer): CustomerDistributor
    {
        $linked = CustomerDistributor::query()
            ->where('customer_id', $customer->id)
            ->where('distributor_id', $distributor->id)
            ->where('relationship_status', CustomerDistributorRelationshipStatus::ACTIVA->value)
            ->first();

        if ($linked === null) {
            abort(422, 'El cliente no pertenece a esta distribuidora.');
        }

        return $linked;
    }

    /**
     * Un cliente nuevo (EN_VERIFICACION) SI puede recibir el vale: la
     * verificación presencial ahora se exige hasta la dispersión en
     * sucursal (ver DisburseVoucherService), no aquí. Pero un cliente
     * BLOQUEADO, MOROSO o INACTIVO no puede recibir un vale nuevo.
     */
    private function assertCustomerEligible(Customer $customer): void
    {
        $blockedStatuses = [
            CustomerStatus::BLOQUEADO,
            CustomerStatus::MOROSO,
            CustomerStatus::INACTIVO,
        ];

        if (in_array($customer->status, $blockedStatuses, true)) {
            abort(422, 'El cliente no puede recibir un vale nuevo por su estado actual.');
        }
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

    /**
     * Un producto con categoria asignada solo puede canjearlo una distribuidora
     * de esa misma categoria (ej. un producto de categoria ORO no lo puede pedir
     * una distribuidora COBRE). Un producto sin categoria (category_id null) es
     * generico: cualquier distribuidora puede canjearlo sin importar la suya.
     */
    private function assertProductMatchesCategory(Distributor $distributor, FinancialProduct $product): void
    {
        if ($product->category_id !== null && $product->category_id !== $distributor->category_id) {
            abort(422, 'El producto seleccionado no está disponible para la categoría de esta distribuidora.');
        }
    }
}
