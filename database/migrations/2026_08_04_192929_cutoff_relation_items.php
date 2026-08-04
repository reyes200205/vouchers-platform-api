<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cutoff_relation_items', function (Blueprint $table) {
            $table->boolean('is_late_payment')
                ->default(false)
                ->after('total_payments');

            $table->smallInteger('installment_number')
                ->nullable()
                ->after('is_late_payment');

            $table->smallInteger('accumulated_late_installments')
                ->default(0)
                ->after('installment_number');

           $table->decimal('previous_paid_amount', 12, 2)
                ->default(0.00)
                ->after('line_total_amount');

            $table->unsignedBigInteger('origin_cutoff_id')
                ->nullable()
                ->after('previous_paid_amount');

            $table->unsignedBigInteger('origin_relation_id')
                ->nullable()
                ->after('origin_cutoff_id');

            $table->foreign('origin_cutoff_id')
                ->references('id')
                ->on('cutoffs')
                ->nullOnDelete();

            $table->foreign('origin_relation_id')
                ->references('id')
                ->on('cutoff_relations')
                ->nullOnDelete();

            $table->index('is_late_payment');
            $table->index(
                ['voucher_id', 'installment_number'],
                'cutoff_items_voucher_installment_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('cutoff_relation_items', function (Blueprint $table) {
            $table->dropForeign(['origin_cutoff_id']);
            $table->dropForeign(['origin_relation_id']);

            $table->dropIndex(['is_late_payment']);
            $table->dropIndex('cutoff_items_voucher_installment_idx');

            $table->dropColumn([
                'is_late_payment',
                'installment_number',
                'accumulated_late_installments',
                'previous_paid_amount',
                'origin_cutoff_id',
                'origin_relation_id',
            ]);
        });
    }
};