<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cutoff_relation_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cutoff_relation_id');
            $table->unsignedBigInteger('voucher_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('product_name_snapshot', 150);
            $table->integer('payments_made')->default(0);
            $table->integer('total_payments')->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0.00);
            $table->decimal('payment_amount', 12, 2)->default(0.00);
            $table->decimal('late_fee_amount', 12, 2)->default(0.00);
            $table->decimal('line_total_amount', 12, 2)->default(0.00);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('cutoff_relation_id')->references('id')->on('cutoff_relations');
            $table->foreign('voucher_id')->references('id')->on('vouchers');
            $table->foreign('customer_id')->references('id')->on('customers');

            $table->index('cutoff_relation_id');
            $table->index('voucher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cutoff_relation_items');
    }
};