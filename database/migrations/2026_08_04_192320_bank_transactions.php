<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_bank_account_id')->nullable();
            $table->string('reference', 100)->nullable();
            $table->date('transaction_date');
            $table->time('transaction_time')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('transaction_type', 50)->nullable();
            $table->string('transaction_number', 100)->nullable();
            $table->string('payer_name', 150)->nullable();
            $table->text('raw_description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_bank_account_id')
                ->references('id')
                ->on('bank_accounts');

            $table->index('reference');
            $table->index(['transaction_date', 'amount']);
            $table->index(['transaction_date', 'id'], 'bank_transactions_date_id_idx');
            $table->index(
                ['reference', 'transaction_date', 'amount'],
                'bank_transactions_reference_date_amount_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};