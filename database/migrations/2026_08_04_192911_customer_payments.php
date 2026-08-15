<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->index('customer_id', 'customer_payments_customer_id_idx');
            $table->index(['distributor_id', 'payment_date'], 'customer_payments_distributor_date_idx');
        });

        Schema::table('cutoff_relation_items', function (Blueprint $table) {
            $table->index('customer_id', 'cutoff_relation_items_customer_id_idx');
        });

        Schema::table('point_movements', function (Blueprint $table) {
            $table->index('customer_payment_id', 'point_movements_customer_payment_id_idx');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->index('issued_at', 'vouchers_issued_at_idx');
            $table->index(['status', 'payment_due_date'], 'vouchers_status_due_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex('vouchers_issued_at_idx');
            $table->dropIndex('vouchers_status_due_date_idx');
        });

        Schema::table('point_movements', function (Blueprint $table) {
            $table->dropIndex('point_movements_customer_payment_id_idx');
        });

        Schema::table('cutoff_relation_items', function (Blueprint $table) {
            $table->dropIndex('cutoff_relation_items_customer_id_idx');
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropIndex('customer_payments_distributor_date_idx');
            $table->dropIndex('customer_payments_customer_id_idx');
        });
    }
};