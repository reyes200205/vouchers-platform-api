<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('distributor_id');
            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->unsignedBigInteger('cutoff_id')->nullable();
            $table->unsignedBigInteger('customer_payment_id')->nullable();

            $table->enum('transaction_type', [
                'GANADO_ANTICIPADO',
                'GANADO_PUNTUAL',
                'PENALIZACION_ATRASO',
                'AJUSTE_MANUAL',
                'REVERSO',
                'CANJE'
            ]);

            $table->decimal('points', 12, 2);
            $table->decimal('point_value_snapshot', 12, 2)->default(2.00);
            $table->string('reason', 255)->nullable();
            $table->timestamp('transaction_date')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('distributor_id')->references('id')->on('distributors');
            $table->foreign('voucher_id')->references('id')->on('vouchers');
            $table->foreign('cutoff_id')->references('id')->on('cutoffs');
            $table->foreign('customer_payment_id')->references('id')->on('customer_payments');

            $table->index('distributor_id');
            $table->index('voucher_id');
            $table->index('cutoff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};