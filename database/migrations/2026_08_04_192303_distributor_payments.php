<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cutoff_relation_id');
            $table->unsignedBigInteger('distributor_id');
            $table->unsignedBigInteger('company_bank_account_id')->nullable();
            $table->decimal('amount', 12, 2);

            $table->enum('payment_method', [
                'TRANSFER',
                'DEPOSIT',
                'OTHER'
            ])->default('TRANSFER');

            $table->string('reported_reference', 100)->nullable();
            $table->dateTime('payment_date');

            $table->enum('status', [
                'REPORTED',
                'DETECTED',
                'RECONCILED',
                'REJECTED'
            ])->default('REPORTED');

            $table->text('notes')->nullable();
            $table->json('voucher_breakdown')->nullable();
            $table->boolean('breakdown_applied')->default(false);

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('cutoff_relation_id')->references('id')->on('cutoff_relations');
            $table->foreign('distributor_id')->references('id')->on('distributors');
            $table->foreign('company_bank_account_id')->references('id')->on('bank_accounts');

            $table->index('cutoff_relation_id');
            $table->index('distributor_id');
            $table->index(['cutoff_relation_id', 'status'], 'distributor_payments_relation_status_idx');
            $table->index(['distributor_id', 'status'], 'distributor_payments_distributor_status_idx');
            $table->index(['distributor_id', 'payment_date'], 'distributor_payments_distributor_date_idx');
            $table->index(['reported_reference', 'status'], 'distributor_payments_reference_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_payments');
    }
};