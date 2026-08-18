<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\CreditIncreaseRequestStatus;
use App\Enums\CutoffRelationStatus;
use App\Enums\CutoffStatus;
use App\Enums\CutoffType;
use App\Enums\DisbursementMethod;
use App\Enums\DistributorStatus;
use App\Enums\PaymentMethod;
use App\Enums\PointRedemptionStatus;
use App\Enums\VoucherStatus;
use App\Models\Application;
use App\Models\ApplicationVerification;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\CreditIncreaseRequest;
use App\Models\Customer;
use App\Models\Cutoff;
use App\Models\CutoffRelation;
use App\Models\CustomerPayment;
use App\Models\Distributor;
use App\Models\DistributorCategory;
use App\Models\FinancialProduct;
use App\Models\Person;
use App\Models\PointRedemption;
use App\Models\PointSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class AlessandroDemoSeeder extends Seeder
{
    public function run(): void
    {
        $gm = $this->userWithRole('alessandro', 'Gerente General (Demo)', 'general_manager', null);

        $products = collect();
        foreach ([
            ['code' => 'VALOR-15000', 'name' => 'Vale por Valor 15K', 'description' => 'Vale de 15,000 MXN a 8 quincenas', 'principal_amount' => 15000.00, 'number_of_fortnights' => 8, 'company_commission_percentage' => 10.0000, 'insurance_amount' => 100.00, 'fortnightly_interest_percentage' => 5.0000, 'late_fee_amount' => 300.00, 'disbursement_method' => DisbursementMethod::TRANSFERENCIA, 'is_active' => true],
            ['code' => 'VALOR-30000', 'name' => 'Vale por Valor 30K', 'description' => 'Vale de 30,000 MXN a 12 quincenas', 'principal_amount' => 30000.00, 'number_of_fortnights' => 12, 'company_commission_percentage' => 10.0000, 'insurance_amount' => 200.00, 'fortnightly_interest_percentage' => 5.0000, 'late_fee_amount' => 400.00, 'disbursement_method' => DisbursementMethod::TRANSFERENCIA, 'is_active' => true],
        ] as $productData) {
            $products->push(FinancialProduct::query()->create($productData));
        }

        $categoryTemplates = [
            ['code' => 'COBRE', 'name' => 'Cobre', 'commission_percentage' => 3.0000, 'points_per_1200' => 3, 'late_penalty_percentage' => 20.0000],
            ['code' => 'PLATA', 'name' => 'Plata', 'commission_percentage' => 6.0000, 'points_per_1200' => 3, 'late_penalty_percentage' => 20.0000],
            ['code' => 'ORO', 'name' => 'Oro', 'commission_percentage' => 10.0000, 'points_per_1200' => 3, 'late_penalty_percentage' => 20.0000],
        ];

        $insuranceTiers = [
            ['min_amount' => 0, 'max_amount' => 7999.99, 'insurance_amount' => 50.00],
            ['min_amount' => 8000, 'max_amount' => 14999.99, 'insurance_amount' => 100.00],
            ['min_amount' => 15000, 'max_amount' => PHP_FLOAT_MAX, 'insurance_amount' => 200.00],
        ];

        $branchProductTemplates = [
            ['name' => 'Vale Zapatería 8K', 'description' => 'Vale de 8,000 MXN a 2 quincenas', 'principal_amount' => 8000.00, 'number_of_fortnights' => 2],
            ['name' => 'Vale Ropa 6K', 'description' => 'Vale de 6,000 MXN a 3 quincenas', 'principal_amount' => 6000.00, 'number_of_fortnights' => 3],
            ['name' => 'Vale Abarrotes 10K', 'description' => 'Vale de 10,000 MXN a 4 quincenas', 'principal_amount' => 10000.00, 'number_of_fortnights' => 4],
        ];

        PointSetting::query()->firstOrCreate([], [
            'point_value_mxn' => 2.00,
            'updated_by_user_id' => $gm->id,
        ]);

        $branchNames = ['Sucursal Centro', 'Sucursal Norte', 'Sucursal Sur'];
        $branches = collect($branchNames)->map(function (string $name, int $index) use ($gm, $categoryTemplates, $branchProductTemplates, $insuranceTiers): Branch {
            $branch = Branch::query()->create([
                'code' => 'SUC-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'address' => "Av. Principal #{$index}00",
                'phone' => '555'.str_pad((string) $index, 7, '0', STR_PAD_LEFT),
                'is_active' => true,
            ]);

            $bm = $this->userWithRole('gerente_sucursal_'.($index + 1), "Gerente {$name}", 'branch_manager', $branch);
            BranchSetting::query()->firstOrCreate(['branch_id' => $branch->id], [
                'updated_by_user_id' => $bm->id,
                'payment_due_days' => 15,
                'pre_vale_max_percentage' => 50.00,
                'pre_vale_tolerance_amount' => 1000.00,
                'point_value_mxn' => 2.00,
                'insurance_rates_json' => $insuranceTiers,
            ]);

            $categories = collect();
            foreach ($categoryTemplates as $categoryData) {
                $categories->push(DistributorCategory::query()->create([
                    'branch_id' => $branch->id,
                    'code' => $categoryData['code'].'-'.$branch->code,
                    'name' => $categoryData['name'],
                    'commission_percentage' => $categoryData['commission_percentage'],
                    'points_per_1200' => $categoryData['points_per_1200'],
                    'late_penalty_percentage' => $categoryData['late_penalty_percentage'],
                    'is_active' => true,
                ]));
            }
            $branch->setAttribute('demo_categories', $categories);

            $template = $branchProductTemplates[$index % count($branchProductTemplates)];
            FinancialProduct::query()->create([
                'branch_id' => $branch->id,
                'category_id' => $categories->first()->id,
                'code' => 'VAL-'.$branch->code.'-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'name' => $template['name'],
                'description' => $template['description'],
                'principal_amount' => $template['principal_amount'],
                'number_of_fortnights' => $template['number_of_fortnights'],
                'company_commission_percentage' => 10.0000,
                'insurance_amount' => 50.00,
                'fortnightly_interest_percentage' => 5.0000,
                'late_fee_amount' => 200.00,
                'disbursement_method' => DisbursementMethod::TRANSFERENCIA,
                'is_active' => true,
            ]);

            return $branch;
        });

        $distributors = collect();
        $people = Person::factory()->count(10)->create();

        foreach ($branches as $branchIndex => $branch) {
            $branchCategories = $branch->getAttribute('demo_categories');
            foreach ($people->forPage($branchIndex + 1, 3) as $person) {
                $distributor = Distributor::query()->create([
                    'person_id' => $person->id,
                    'branch_id' => $branch->id,
                    'category_id' => $branchCategories[$branchIndex % $branchCategories->count()]->id,
                    'distributor_number' => 'DIST-'.str_pad((string) ($distributors->count() + 1), 8, '0', STR_PAD_LEFT),
                    'status' => DistributorStatus::ACTIVA,
                    'credit_limit' => 50000 + ($distributors->count() * 25000),
                    'available_credit' => 30000 + ($distributors->count() * 10000),
                    'current_points' => 300 + ($distributors->count() * 150),
                    'can_issue_vouchers' => true,
                    'is_external' => false,
                    'activated_at' => now()->subMonths(10),
                ]);

                $distributorUser = $this->userWithRole(
                    'distribuidora_'.($distributors->count() + 1),
                    $person->first_name.' '.$person->last_name,
                    'distributor',
                    $branch,
                    $person
                );

                BankAccount::query()->create([
                    'owner_type' => 'DISTRIBUIDORA',
                    'owner_id' => $distributor->id,
                    'bank' => 'Banco Demo',
                    'account_holder_name' => $person->first_name.' '.$person->last_name,
                    'masked_account_number' => '****'.random_int(1000, 9999),
                    'clabe' => '012'.str_pad((string) random_int(0, 999999999999999), 15, '0', STR_PAD_LEFT),
                    'reference_base' => 'REF-'.$distributor->id,
                    'is_primary' => true,
                ]);

                $distributors->push($distributor);
            }
        }

        $customers = Customer::factory()->count(15)->create();

        foreach ($distributors->take(4) as $index => $distributor) {
            $application = Application::query()->create([
                'applicant_person_id' => Person::factory()->create()->id,
                'branch_id' => $distributor->branch_id,
                'captured_by_user_id' => $distributor->coordinator_user_id ?? $gm->id,
                'coordinator_user_id' => $gm->id,
                'status' => ApplicationStatus::POSIBLE_DISTRIBUIDORA,
                'initial_category_code' => 'COPPER',
                'requested_credit_limit' => 60000.00,
                'credit_bureau_result' => 'APROBADO',
                'prevale_approved' => true,
                'house_photos_complete' => true,
                'submitted_at' => now()->subDays($index + 1),
                'taken_at' => now()->subDays($index + 2),
                'reviewed_at' => now()->subDays($index + 1)->addHours(5),
            ]);

            $verifier = User::factory()->create();
            $this->attachRole($verifier, 'verifier', $application->branch_id);
            ApplicationVerification::query()->create([
                'application_id' => $application->id,
                'verifier_user_id' => $verifier->id,
                'result' => 'VERIFICADA',
                'notes' => 'Verificacion de domicilio completada.',
                'verification_latitude' => 19.4326000,
                'verification_longitude' => -99.1332000,
                'visit_date' => now()->subDay(),
                'checklist_json' => ['fachada' => true, 'vivienda' => true, 'identificacion' => true],
                'distance_meters' => 120.50,
            ]);
        }

        foreach ($distributors->take(4) as $index => $distributor) {
            CreditIncreaseRequest::query()->create([
                'distributor_id' => $distributor->id,
                'branch_id' => $distributor->branch_id,
                'requested_by_user_id' => $distributor->coordinator_user_id ?? $gm->id,
                'requested_amount' => 30000.00,
                'reason' => 'Crecimiento de cartera: nuevos clientes programados.',
                'status' => CreditIncreaseRequestStatus::PRE_AUTORIZADO,
                'pre_authorized_amount' => 25000.00,
                'pre_authorized_by_user_id' => $gm->id,
                'pre_authorized_at' => now()->subHours($index + 3),
                'created_at' => now()->subDays($index + 1),
            ]);
        }

        foreach ($distributors->take(4) as $index => $distributor) {
            PointRedemption::query()->create([
                'distributor_id' => $distributor->id,
                'branch_id' => $distributor->branch_id,
                'requested_by_user_id' => $distributor->coordinator_user_id ?? $gm->id,
                'points' => 200.00,
                'point_value_snapshot' => 2.00,
                'amount_mxn' => 400.00,
                'status' => PointRedemptionStatus::PENDIENTE,
                'created_at' => now()->subDays($index + 2),
            ]);
        }

        $this->seedVouchersAndPayments($distributors, $customers, $products, $gm);

        $this->seedCutoffRelations($distributors->take(4), $gm);
    }

    private function userWithRole(string $username, string $displayName, string $roleCode, ?Branch $branch, ?Person $person = null): User
    {
        $person = $person ?? Person::query()->create([
            'first_name' => $displayName,
            'last_name' => 'Demo',
        ]);

        $user = User::query()->firstOrCreate(['username' => $username], [
            'person_id' => $person->id,
            'password_hash' => Hash::make('password'),
            'is_active' => true,
            'requires_vpn' => false,
            'login_channel' => 'WEB',
        ]);

        $this->attachRole($user, $roleCode, $branch?->id);

        return $user;
    }

    private function attachRole(User $user, string $roleCode, ?int $branchId): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleCode, 'guard_name' => 'web'],
            ['code' => $roleCode, 'description' => ucfirst($roleCode), 'is_active' => true]
        );

        if (! $user->businessRoles()->wherePivot('role_id', $role->id)->exists()) {
            $user->businessRoles()->attach($role, [
                'branch_id' => $branchId,
                'assigned_at' => now(),
                'is_primary' => true,
            ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Distributor>  $distributors
     * @param  \Illuminate\Support\Collection<int, Customer>  $customers
     * @param  \Illuminate\Support\Collection<int, FinancialProduct>  $products
     */
    private function seedVouchersAndPayments($distributors, $customers, $products, User $gm): void
    {
        $states = [
            VoucherStatus::PAGADO,
            VoucherStatus::ACTIVO,
            VoucherStatus::PAGO_PARCIAL,
            VoucherStatus::MOROSO,
        ];

        for ($month = 11; $month >= 0; $month--) {
            $date = now()->startOfMonth()->subMonths($month)->addDays(random_int(3, 20));

            foreach ($distributors->take(3) as $distributor) {
                $product = $products[$month % 2];
                $customer = $customers->random();
                $status = $states[array_rand($states)];

                $voucher = Voucher::factory()->create([
                    'distributor_id' => $distributor->id,
                    'customer_id' => $customer->id,
                    'financial_product_id' => $product->id,
                    'branch_id' => $distributor->branch_id,
                    'status' => $status,
                    'amount' => $product->principal_amount,
                    'total_debt_amount' => $product->principal_amount * 1.5,
                    'fortnightly_payment_amount' => $product->principal_amount / $product->number_of_fortnights,
                    'total_fortnights' => $product->number_of_fortnights,
                    'payments_made' => 0,
                    'current_balance' => $product->principal_amount * 1.5,
                    'issued_at' => $date,
                ]);

                $paymentDate = $date->copy()->addDays(random_int(5, 15));
                CustomerPayment::query()->create([
                    'voucher_id' => $voucher->id,
                    'customer_id' => $customer->id,
                    'distributor_id' => $distributor->id,
                    'collected_by_user_id' => $gm->id,
                    'payment_date' => $paymentDate,
                    'amount' => round((float) $voucher->fortnightly_payment_amount, 2),
                    'payment_method' => PaymentMethod::EFECTIVO,
                    'is_partial' => $month % 2 === 0,
                    'affects_points' => true,
                    'notes' => 'Pago demo',
                ]);
            }
        }

        CustomerPayment::query()->create([
            'voucher_id' => Voucher::first()->id,
            'customer_id' => $customers->first()->id,
            'distributor_id' => $distributors->first()->id,
            'collected_by_user_id' => $gm->id,
            'payment_date' => now(),
            'amount' => 2350.00,
            'payment_method' => PaymentMethod::TRANSFERENCIA,
            'is_partial' => true,
            'affects_points' => true,
            'notes' => 'Cobro del dia',
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Distributor>  $distributors
     */
    private function seedCutoffRelations($distributors, User $gm): void
    {
        foreach ($distributors as $index => $distributor) {
            $cutoff = Cutoff::query()->create([
                'branch_id' => $distributor->branch_id,
                'cutoff_type' => CutoffType::PAGOS,
                'base_day_of_month' => now()->day,
                'base_time' => now()->format('H:i:s'),
                'scheduled_at' => now()->subMonth()->startOfMonth(),
                'executed_at' => now()->subMonth()->startOfMonth()->addHours(2),
                'keep_date_on_holiday' => true,
                'status' => CutoffStatus::EJECUTADO,
            ]);

            CutoffRelation::query()->create([
                'cutoff_id' => $cutoff->id,
                'distributor_id' => $distributor->id,
                'relation_number' => 'REL-'.$cutoff->id.'-'.$distributor->id,
                'payment_due_date' => now()->subDays(5),
                'credit_limit_snapshot' => $distributor->credit_limit,
                'available_credit_snapshot' => $distributor->available_credit,
                'points_snapshot' => $distributor->current_points,
                'total_commission' => 4000.00,
                'total_payment' => 18000.00,
                'total_late_fees' => $index % 2 === 0 ? 600.00 : 0.00,
                'total_amount_due' => 18600.00,
                'status' => $index % 2 === 0 ? CutoffRelationStatus::VENCIDA : CutoffRelationStatus::PAGADA,
                'generated_at' => now()->subMonth()->startOfMonth()->addHours(2),
            ]);
        }
    }
}