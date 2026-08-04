<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('distributor_payment_id');
            $table->unsignedBigInteger('bank_transaction_id');
            $table->unsignedBigInteger('reconciled_by_user_id')->nullable();
            $table->timestamp('reconciled_at')->useCurrent();
            $table->decimal('reconciled_amount', 12, 2);
            $table->decimal('amount_difference', 12, 2)->default(0.00);

            $table->enum('status', [
                'CONCILIADA',
                'CON_DIFERENCIA',
                'RECHAZADA'
            ])->default('CONCILIADA');

            $table->text('notes')->nullable();

            $table->foreign('distributor_payment_id')
                ->references('id')
                ->on('distributor_payments');

            $table->foreign('bank_transaction_id')
                ->references('id')
                ->on('bank_transactions');

            $table->foreign('reconciled_by_user_id')
                ->references('id')
                ->on('users');

            $table->unique(
                ['distributor_payment_id', 'bank_transaction_id'],
                'reconciliation_payment_transaction_unique'
            );

            $table->unique(
                'bank_transaction_id',
                'reconciliation_transaction_unique'
            );

            $table->unique(
                'distributor_payment_id',
                'reconciliation_payment_unique'
            );

            $table->index('distributor_payment_id');
            $table->index('bank_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliations');
    }
};