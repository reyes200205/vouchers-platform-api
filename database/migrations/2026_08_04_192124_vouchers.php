<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('voucher_number', 50)->unique();
            $table->unsignedBigInteger('distributor_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('financial_product_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();

            $table->enum('status', [
                'BORRADOR', 
                'APROBADO', 
                'TRANSFERIDO', 
                'ACTIVO', 
                'PAGO_PARCIAL', 
                'PAGADO', 
                'LIQUIDADO', 
                'MOROSO', 
                'RECLAMADO', 
                'CANCELADO', 
                'REVERSADO'
            ])->default('BORRADOR');

            $table->decimal('amount', 12, 2);
            $table->decimal('company_commission_percentage_snapshot', 8, 4)->default(0.0000);
            $table->decimal('company_commission_amount', 12, 2)->default(0.00);
            $table->decimal('insurance_amount_snapshot', 12, 2)->default(0.00);
            $table->decimal('interest_percentage_snapshot', 8, 4)->default(0.0000);
            $table->decimal('interest_amount', 12, 2)->default(0.00);
            $table->decimal('distributor_profit_percentage_snapshot', 8, 4)->default(0.0000);
            $table->decimal('distributor_profit_amount', 12, 2)->default(0.00);
            $table->decimal('late_fee_amount_snapshot', 12, 2)->default(0.00);
            $table->decimal('total_debt_amount', 12, 2);
            $table->decimal('fortnightly_payment_amount', 12, 2);
            $table->integer('total_fortnights');
            $table->integer('payments_made')->default(0);
            $table->decimal('current_balance', 12, 2);

            $table->string('transfer_reference', 100)->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('transferred_at')->nullable();
            $table->date('payment_due_date')->nullable();
            $table->date('early_payment_start_date')->nullable();
            $table->date('early_payment_end_date')->nullable();

            $table->text('claim_reason')->nullable();
            $table->boolean('is_canceled')->default(false);
            $table->dateTime('canceled_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('distributor_id')->references('id')->on('distributors');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('financial_product_id')->references('id')->on('financial_products');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by_user_id')->references('id')->on('users');
            $table->foreign('approved_by_user_id')->references('id')->on('users');

            $table->index('distributor_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index(['distributor_id', 'status'], 'vouchers_distributor_status_idx');
            $table->index(['distributor_id', 'customer_id', 'status'], 'vouchers_distributor_customer_status_idx');
            $table->index(['distributor_id', 'issued_at', 'id'], 'vouchers_distributor_issue_id_idx');
            $table->index(['distributor_id', 'payment_due_date', 'issued_at'], 'vouchers_distributor_due_issue_idx');
            $table->index(['branch_id', 'status'], 'vouchers_branch_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};