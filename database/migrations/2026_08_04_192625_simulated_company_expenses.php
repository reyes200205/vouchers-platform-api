<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulated_company_expenses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('voucher_id');
            $table->unsignedBigInteger('distributor_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('executed_by_user_id')->nullable();

            $table->string('source', 40)->default('VALE_FERIADO');
            $table->string('internal_reference', 120)->unique();

            $table->decimal('amount', 12, 2);
            $table->dateTime('operation_date');

            $table->text('notes')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('voucher_id')->references('id')->on('vouchers');
            $table->foreign('distributor_id')->references('id')->on('distributors');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('executed_by_user_id')->references('id')->on('users');

            $table->unique('voucher_id');
            $table->index(['distributor_id', 'operation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulated_company_expenses');
    }
};