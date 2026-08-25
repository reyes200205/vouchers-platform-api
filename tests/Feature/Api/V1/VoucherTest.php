<?php

declare(strict_types=1);

use App\Enums\CustomerDistributorRelationshipStatus;
use App\Enums\CustomerStatus;
use App\Enums\VoucherStatus;
use App\Mail\VoucherIssuedMail;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDistributor;
use App\Models\CutoffRelation;
use App\Models\Distributor;
use App\Models\DistributorCategory;
use App\Models\FinancialProduct;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function signInBusinessRole(User $user, string $roleCode, Branch $branch): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

function signInDistributor(User $user, Distributor $distributor): void
{
    $role = Role::query()->firstOrCreate(['code' => 'distributor'], ['name' => 'distributor']);
    $user->update(['person_id' => $distributor->person_id]);
    $user->businessRoles()->attach($role, [
        'branch_id' => $distributor->branch_id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

function voucherScenario(): array
{
    $branch = Branch::factory()->create();
    $category = DistributorCategory::factory()->create(['commission_percentage' => 8.0000]);
    $product = FinancialProduct::factory()->create();
    $distributor = Distributor::factory()->create([
        'branch_id' => $branch->id,
        'category_id' => $category->id,
        'credit_limit' => 30000,
        'available_credit' => 30000,
    ]);
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'status' => CustomerStatus::ACTIVO,
        'verified_at' => now(),
    ]);
    CustomerDistributor::query()->create([
        'distributor_id' => $distributor->id,
        'customer_id' => $customer->id,
        'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
        'prevale_approved' => false,
        'blocked_due_to_relationship' => false,
        'linked_at' => now(),
    ]);

    return compact('branch', 'category', 'product', 'distributor', 'customer');
}

describe('Voucher request (pre-issue por la distribuidora)', function (): void {
    it('creates a pre-vale request when the distributor has 100% of credit available', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()
            ->assertJsonPath('data.is_pre_vale', true)
            ->assertJsonPath('data.status', 'PENDIENTE')
            ->assertJsonPath('data.requested_amount', '15000.00')
            ->assertJsonPath('data.snapshot.total_debt_amount', 22600)
            ->assertJsonPath('data.snapshot.fortnightly_payment_amount', 2825);

        $this->assertDatabaseHas('voucher_requests', [
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'is_pre_vale' => true,
            'status' => 'PENDIENTE',
        ]);
    });

    it('denies a first voucher beyond 50% plus tolerance', function (): void {
        ['branch' => $branch, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $product = FinancialProduct::factory()->create(['principal_amount' => 17000.00]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El monto supera el máximo permitido para el primer vale (50% del crédito disponible).');

        $this->assertDatabaseCount('voucher_requests', 0);
    });

    it('does not apply the 50% rule once the distributor is not at 100% available and there is no pending reactivation', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributor->update(['credit_limit' => 30000, 'available_credit' => 25000]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()
            ->assertJsonPath('data.is_pre_vale', false);
    });

    it('reapplies the 50% rule after a credit increase even if the distributor is not at 100% available', function (): void {
        // Regla confirmada: si el gerente autoriza un aumento de línea mientras
        // la distribuidora ya tiene crédito usado, el siguiente vale vuelve a
        // respetar el 50% del DISPONIBLE + tolerancia, aunque el disponible no
        // sea igual al límite total (ver Distributor::prevale_required_after_credit_increase_at).
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributor->update([
            'credit_limit' => 30000,
            'available_credit' => 25000, // ya tiene 5,000 usados
            'prevale_required_after_credit_increase_at' => now(),
        ]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El monto supera el máximo permitido para el primer vale (50% del crédito disponible).');

        $this->assertDatabaseCount('voucher_requests', 0);
    });

    it('allows a request for a customer that is not verified yet (verification happens at disbursement)', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor] = voucherScenario();
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
            'status' => CustomerStatus::EN_VERIFICACION,
            'verified_at' => null,
        ]);
        CustomerDistributor::query()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
        ]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated();

        $this->assertDatabaseHas('voucher_requests', [
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'status' => 'PENDIENTE',
        ]);
    });

    it('rejects a request for a customer that is blocked, delinquent or inactive', function (string $status): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor] = voucherScenario();
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
            'status' => $status,
        ]);
        CustomerDistributor::query()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
        ]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El cliente no puede recibir un vale nuevo por su estado actual.');

        $this->assertDatabaseCount('voucher_requests', 0);
    })->with([
        'bloqueado' => CustomerStatus::BLOQUEADO->value,
        'moroso' => CustomerStatus::MOROSO->value,
        'inactivo' => CustomerStatus::INACTIVO->value,
    ]);

    it('rejects a request for a customer that already has a pending voucher request', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated();

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El cliente ya tiene una solicitud de vale pendiente de aprobación.');

        $this->assertDatabaseCount('voucher_requests', 1);
    });

    it('activates the customer when their pre-vale request is approved', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor] = voucherScenario();
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
            'status' => CustomerStatus::EN_VERIFICACION,
            'verified_at' => null,
        ]);
        CustomerDistributor::query()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
        ]);
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $requestId = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data.id');

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/voucher-requests/{$requestId}/approve")
            ->assertOk();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'ACTIVO',
        ]);
        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
            'verified_at' => null,
        ]);
    });

    it('rejects a request for a customer with an active voucher with balance', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        Voucher::factory()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'status' => VoucherStatus::ACTIVO,
            'current_balance' => 5000.00,
        ]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('voucher_requests', 0);
    });

    it('rejects a request for a customer not linked to the distributor', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor] = voucherScenario();
        $otherCustomer = Customer::factory()->create(['branch_id' => $branch->id]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $otherCustomer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('voucher_requests', 0);
    });

    it('rejects a request for a product tied to a different distributor category', function (): void {
        ['branch' => $branch, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $otherCategory = DistributorCategory::factory()->create();
        $product = FinancialProduct::factory()->create(['category_id' => $otherCategory->id]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El producto seleccionado no está disponible para la categoría de esta distribuidora.');

        $this->assertDatabaseCount('voucher_requests', 0);
    });

    it('allows a request for a product tied to the distributor own category', function (): void {
        ['branch' => $branch, 'category' => $category, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $product = FinancialProduct::factory()->create(['category_id' => $category->id]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated();
    });

    it('allows a request for a product with no category regardless of the distributor category', function (): void {
        ['branch' => $branch, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $product = FinancialProduct::factory()->create(['category_id' => null]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated();
    });

    it('rejects a request when available credit is not enough to cover the voucher principal', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        // Producto por default: principal 15,000 -- 10,000 disponibles no alcanzan.
        $distributor->update(['credit_limit' => 30000, 'available_credit' => 10000]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El crédito disponible de la distribuidora es insuficiente para cubrir el monto del vale.');

        $this->assertDatabaseCount('voucher_requests', 0);
    });

    it('rejects a request when the distributor is blocked (MOROSA/can_issue_vouchers=false) for consecutive overdue cutoffs', function (): void {
        ['product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributor->update([
            'status' => \App\Enums\DistributorStatus::MOROSA,
            'can_issue_vouchers' => false,
        ]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'La distribuidora está bloqueada por adeudo vencido y no puede pedir vales nuevos hasta regularizar el pago.');

        $this->assertDatabaseCount('voucher_requests', 0);
    });

    it('emails the customer as soon as the request is created, with distributor name, number, dates and amount', function (): void {
        Mail::fake();

        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        \App\Models\BranchSetting::query()->updateOrCreate(
            ['branch_id' => $branch->id],
            ['voucher_expiration_days' => 15]
        );
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $voucherRequest = \App\Models\VoucherRequest::query()->findOrFail($request['id']);
        $voucherNumber = 'V-'.$voucherRequest->id;

        Mail::assertSent(VoucherIssuedMail::class, function (VoucherIssuedMail $mail) use ($voucherRequest, $customer, $distributor, $voucherNumber) {
            $rendered = $mail->render();

            return $mail->hasTo($customer->person->email)
                && $mail->voucherNumber === $voucherNumber
                && str_contains($rendered, $voucherNumber)
                && str_contains($rendered, $distributor->person->first_name)
                && str_contains($rendered, $voucherRequest->created_at->translatedFormat('d/m/Y'))
                && str_contains($rendered, $voucherRequest->created_at->copy()->addDays(15)->translatedFormat('d/m/Y'))
                && str_contains($rendered, number_format((float) $voucherRequest->requested_amount, 2));
        });
    });

    it('does not fail the request when the customer has no email on file', function (): void {
        Mail::fake();

        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $customer->person->update(['email' => null]);
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated();

        Mail::assertNothingSent();
    });
});

describe('Voucher approval (cajera/gerente)', function (): void {
    it('approves the request, creates the voucher and decrements available credit', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $response = $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVO')
            ->assertJsonPath('data.is_pre_vale', true)
            ->assertJsonPath('data.voucher_number', 'V-1')
            ->assertJsonPath('data.total_debt_amount', '22600.00')
            ->assertJsonPath('data.fortnightly_payment_amount', '2825.00')
            ->assertJsonPath('data.approved_by_user_id', $cashier->id)
            ->assertJsonPath('data.disbursed_by_user_id', $cashier->id);

        expect($response->json('data.transfer_reference'))->not->toBeNull();
        expect($response->json('data.authorized_number'))->not->toBeNull();
        expect($response->json('data.payment_due_date'))->not->toBeNull();

        $this->assertDatabaseHas('vouchers', [
            'voucher_number' => 'V-1',
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'status' => 'ACTIVO',
            'is_pre_vale' => true,
            'current_balance' => 22600.00,
        ]);

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'available_credit' => 15000.00,
        ]);

        $this->assertDatabaseHas('voucher_requests', [
            'id' => $request['id'],
            'status' => 'APROBADO',
            'decided_by_user_id' => $cashier->id,
        ]);

        $this->assertDatabaseHas('customer_distributor', [
            'customer_id' => $customer->id,
            'distributor_id' => $distributor->id,
            'prevale_approved' => true,
        ]);
    });

    it('clears the credit-increase reactivation flag once the next voucher is approved', function (): void {
        ['branch' => $branch, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $product = FinancialProduct::factory()->create(['principal_amount' => 10000.00]);
        $distributor->update([
            'credit_limit' => 30000,
            'available_credit' => 25000,
            'prevale_required_after_credit_increase_at' => now(),
        ]);
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.is_pre_vale', true);

        expect($distributor->refresh()->prevale_required_after_credit_increase_at)->toBeNull();
        expect((float) $distributor->available_credit)->toBe(15000.00);
    });

    it('rejects approval when the distributor no longer has enough credit', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $distributor->decrement('available_credit', 24000);

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")
            ->assertStatus(422)
            ->assertJsonPath('message', 'El crédito disponible de la distribuidora es insuficiente para aprobar el vale.');

        $this->assertDatabaseCount('vouchers', 0);
    });

    it('rejects approving a request when the distributor became blocked (MOROSA) after the request was already pending', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        // La distribuidora se atrasó y quedó MOROSA DESPUES de pedir el vale
        // (mientras la solicitud seguía pendiente de aprobación) -- no debe
        // poder recibirlo de todos modos.
        $distributor->update([
            'status' => \App\Enums\DistributorStatus::MOROSA,
            'can_issue_vouchers' => false,
        ]);

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")
            ->assertStatus(422)
            ->assertJsonPath('message', 'La distribuidora está bloqueada por adeudo vencido y no puede recibir vales nuevos hasta regularizar el pago.');

        $this->assertDatabaseCount('vouchers', 0);
    });

    it('forbids the distributor from approving requests', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")->assertForbidden();
    });

    it('forbids a coordinator from approving requests', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $coordinator = User::factory()->create();
        signInBusinessRole($coordinator, 'coordinator', $branch);

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")->assertForbidden();
    });

    it('rejects approval when the customer has not been verified by the cashier', function (): void {
        // Solo el PRIMER vale de un cliente puede ser pre-vale (y ese si
        // auto-verifica al cliente al aprobarse -- ver ApproveVoucherService).
        // Para llegar a la red de seguridad hace falta una solicitud NO
        // pre-vale con un cliente que, por alguna razon, sigue sin verificar
        // -- se construye directo con el factory, sin pasar por
        // RequestVoucherService, igual que antes se hacia con Voucher::factory().
        ['branch' => $branch, 'distributor' => $distributor] = voucherScenario();
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
            'status' => CustomerStatus::EN_VERIFICACION,
            'verified_at' => null,
        ]);
        $voucherRequest = VoucherRequest::factory()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'is_pre_vale' => false,
            'requested_amount' => 15000.00,
        ]);

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/voucher-requests/{$voucherRequest->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('message', 'El cliente debe ser verificado antes de poder otorgarle el vale.');

        $this->assertDatabaseCount('vouchers', 0);
    });

    it('assigns the first payment_due_date to the CURRENT cutoff period (revision del profesor: la relacion nueva debe aparecer en el corte que ya esta abierto, no en el siguiente)', function (): void {
        // El dia 27 cae en el periodo 16-31 (branch_settings.cutoff_day = 15 por
        // default). El vale se acaba de otorgar; su primer pago debe caer
        // dentro de ESE MISMO periodo (31 de agosto), no en el que sigue --
        // asi, si ya hay un corte abierto para 16-31 de agosto, esta relacion
        // aparece ahi al reprocesarlo. Ver CutoffPeriodCalculator::currentPeriodEnd().
        $this->travelTo(now()->setDate(2026, 8, 27)->setTime(10, 0, 0));

        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.payment_due_date', '2026-08-31');

        $this->assertDatabaseHas('vouchers', [
            'voucher_number' => 'V-1',
            'payment_due_date' => '2026-08-31 00:00:00',
        ]);
    });

    it('makes a newly-approved voucher show up when the CURRENTLY OPEN cutoff is reprocessed', function (): void {
        // El escenario exacto que se reporto: van en un corte abierto (16-31
        // de agosto), la distribuidora ya tiene una relacion ahi por otro
        // cliente, y le otorgan un vale a un cliente nuevo. Al reprocesar ese
        // MISMO corte, el vale nuevo debe aparecer -- no quedar oculto hasta
        // el siguiente periodo. Como es la MISMA distribuidora, se agrega
        // como un item nuevo a su relacion existente en este corte (una
        // relacion por corte+distribuidora, no una por cliente -- ver
        // GenerateCutoffService::generateRelation()), pero con su propio
        // customer_id/voucher_id, distinto del item del cliente original.
        $this->travelTo(now()->setDate(2026, 8, 27)->setTime(10, 0, 0));

        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $existingCustomer] = voucherScenario();

        Voucher::factory()->create([
            'branch_id' => $branch->id,
            'distributor_id' => $distributor->id,
            'customer_id' => $existingCustomer->id,
            'status' => VoucherStatus::ACTIVO,
            'payment_due_date' => '2026-08-31',
            'fortnightly_payment_amount' => 1000.00,
            'total_debt_amount' => 8000.00,
            'total_fortnights' => 8,
            'distributor_profit_amount' => 400.00,
        ]);

        $branchManager = User::factory()->create();
        signInBusinessRole($branchManager, 'branch_manager', $branch);
        $cutoff = $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => '2026-08-16',
            'period_end' => '2026-08-31',
        ])->assertCreated()->json('data');

        $this->assertDatabaseCount('cutoff_relations', 1);

        $newCustomer = Customer::factory()->create(['branch_id' => $branch->id]);
        CustomerDistributor::query()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $newCustomer->id,
            'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
            'linked_at' => now(),
        ]);

        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);
        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $newCustomer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);
        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.payment_due_date', '2026-08-31');

        // El reprocess requiere permiso de cutoffs.manage (branch_manager o
        // general_manager) -- el ultimo usuario autenticado en este punto es
        // la cajera (que solo puede aprobar/rechazar vales), asi que hay que
        // volver a autenticar al gerente antes de llamarlo. No se vuelve a
        // llamar signInBusinessRole() -- ya tiene el rol asignado desde que
        // genero el corte, y reasignarlo violaria el indice unico de
        // model_has_roles -- solo hace falta cambiar de nuevo el usuario
        // autenticado.
        Sanctum::actingAs($branchManager);
        $this->postJson("/api/v1/cutoffs/{$cutoff['id']}/reprocess")->assertOk();

        // Sigue siendo UNA sola relacion para esta distribuidora en este
        // corte (no una por cliente), pero ahora con dos items: el original
        // (cliente existente) y el nuevo (cliente nuevo, vale recien
        // aprobado) -- ambos visibles en el corte actual, no en el siguiente.
        $this->assertDatabaseCount('cutoff_relations', 1);
        $relation = CutoffRelation::query()
            ->where('cutoff_id', $cutoff['id'])
            ->where('distributor_id', $distributor->id)
            ->firstOrFail();
        $this->assertSame(2, $relation->items()->count());
        $newVoucherId = Voucher::query()->where('customer_id', $newCustomer->id)->value('id');
        $this->assertDatabaseHas('cutoff_relation_items', [
            'cutoff_relation_id' => $relation->id,
            'customer_id' => $newCustomer->id,
            'voucher_id' => $newVoucherId,
        ]);
    });

    it('assigns the first payment_due_date to the branch open cutoff even when it is far from the real calendar date', function (): void {
        // Lo que se reporto en produccion: la sucursal habia generado y
        // cerrado varios cortes de prueba muy adelantados respecto al reloj
        // real (ej. llegaron hasta un corte de noviembre mientras la fecha
        // real seguia siendo agosto). Un vale nuevo NO debe calcularse contra
        // el reloj real en ese caso -- debe caer en el periodo del corte que
        // la sucursal ya tiene abierto (sin cerrar), sea cual sea la fecha
        // real, para que aparezca ahi al reprocesarlo.
        $this->travelTo(now()->setDate(2026, 8, 25)->setTime(10, 0, 0));

        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();

        $branchManager = User::factory()->create();
        signInBusinessRole($branchManager, 'branch_manager', $branch);

        // Cortes de prueba, generados en secuencia (los periodos deben ser
        // consecutivos -- ver GenerateCutoffService::execute()) y cerrados
        // uno por uno, muy adelantados respecto al 25/08 real -- como en el
        // caso reportado (la sucursal llego hasta un corte de noviembre).
        $periods = [
            ['2026-09-01', '2026-09-15'],
            ['2026-09-16', '2026-09-30'],
            ['2026-10-01', '2026-10-15'],
            ['2026-10-16', '2026-10-31'],
        ];
        foreach ($periods as [$start, $end]) {
            $created = $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
                'period_start' => $start,
                'period_end' => $end,
            ])->assertCreated()->json('data');
            \Illuminate\Support\Facades\DB::table('cutoffs')->where('id', $created['id'])->update(['status' => 'CERRADO']);
        }

        // El corte MAS RECIENTE, todavia SIN cerrar -- este es el "abierto".
        $openCutoff = $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => '2026-11-01',
            'period_end' => '2026-11-15',
        ])->assertCreated()->json('data');

        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);
        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        // El vale debe caer en el periodo del corte de noviembre (el
        // abierto), NO en el 31/08 que tocaria segun el reloj real (25/08 +
        // cutoff_day 15 -> periodo 16-31 agosto).
        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.payment_due_date', '2026-11-15');

        $this->assertDatabaseHas('vouchers', [
            'voucher_number' => 'V-'.$request['id'],
            'payment_due_date' => '2026-11-15 00:00:00',
        ]);

        Sanctum::actingAs($branchManager);
        $this->postJson("/api/v1/cutoffs/{$openCutoff['id']}/reprocess")->assertOk();

        $relation = CutoffRelation::query()
            ->where('cutoff_id', $openCutoff['id'])
            ->where('distributor_id', $distributor->id)
            ->first();
        $this->assertNotNull($relation, 'la relacion debio aparecer en el corte abierto (noviembre), no en el que tocaria segun la fecha real');
    });

    it('rejects approving a request that was already decided', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")->assertOk();

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")
            ->assertStatus(422)
            ->assertJsonPath('message', 'La solicitud ya fue resuelta.');

        $this->assertDatabaseCount('vouchers', 1);
    });
});

describe('Voucher rejection (cajera/gerente)', function (): void {
    it('rejects a pending request without touching the credit (nothing was reserved yet)', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('distributors', ['id' => $distributor->id, 'available_credit' => 30000.00]);

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/reject", [
            'rejection_reason' => 'No paso la verificacion de identidad.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'RECHAZADO')
            ->assertJsonPath('data.rejection_reason', 'No paso la verificacion de identidad.');

        $this->assertDatabaseHas('voucher_requests', [
            'id' => $request['id'],
            'status' => 'RECHAZADO',
            'decided_by_user_id' => $cashier->id,
        ]);
        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseHas('distributors', ['id' => $distributor->id, 'available_credit' => 30000.00]);
    });

    it('requires a reason to reject', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/reject", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rejection_reason');
    });

    it('forbids the distributor from rejecting requests', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/reject", ['rejection_reason' => 'x'])
            ->assertForbidden();
    });
});

describe('Voucher views', function (): void {
    it('lets a distributor see only its own vouchers', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        Voucher::factory()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'financial_product_id' => $product->id,
            'status' => VoucherStatus::APROBADO,
        ]);

        $otherDistributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        Voucher::factory()->create([
            'distributor_id' => $otherDistributor->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'financial_product_id' => $product->id,
            'status' => VoucherStatus::APROBADO,
        ]);

        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->getJson('/api/v1/distributor/vouchers')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.distributor_id', $distributor->id);
    });
});
