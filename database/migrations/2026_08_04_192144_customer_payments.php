<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('voucher_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('distributor_id');
            $table->unsignedBigInteger('collected_by_user_id')->nullable();
            $table->dateTime('payment_date');
            $table->decimal('amount', 12, 2);

            $table->enum('payment_method', ['EFECTIVO', 'TRANSFERENCIA'])->default('EFECTIVO');

            $table->boolean('is_partial')->default(false);
            $table->boolean('affects_points')->default(true);
            $table->text('notes')->nullable();

            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by_user_id')->nullable();
            $table->text('reversal_reason')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('voucher_id')->references('id')->on('vouchers');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('distributor_id')->references('id')->on('distributors');
            $table->foreign('collected_by_user_id')->references('id')->on('users');
            $table->foreign('reversed_by_user_id')->references('id')->on('users');

            $table->index('voucher_id');
            $table->index('payment_date');
            $table->index('distributor_id');
            $table->index('reversed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
    }
};