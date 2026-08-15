<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cutoff_relations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cutoff_id');
            $table->unsignedBigInteger('distributor_id');
            $table->string('relation_number', 50)->unique();
            $table->string('payment_reference', 100)->nullable();
            $table->date('payment_due_date');
            $table->date('early_payment_start_date')->nullable();
            $table->date('early_payment_end_date')->nullable();
            $table->decimal('credit_limit_snapshot', 12, 2)->default(0.00);
            $table->decimal('available_credit_snapshot', 12, 2)->default(0.00);
            $table->decimal('points_snapshot', 12, 2)->default(0.00);
            $table->decimal('total_commission', 12, 2)->default(0.00);
            $table->decimal('total_payment', 12, 2)->default(0.00);
            $table->decimal('total_late_fees', 12, 2)->default(0.00);
            $table->decimal('total_amount_due', 12, 2)->default(0.00);

            $table->enum('status', ['GENERADA', 'PAGADA', 'PARCIAL', 'VENCIDA', 'CERRADA'])->default('GENERADA');

            $table->timestamp('generated_at')->useCurrent();

            $table->foreign('cutoff_id')->references('id')->on('cutoffs');
            $table->foreign('distributor_id')->references('id')->on('distributors');

            $table->index('cutoff_id');
            $table->index('distributor_id');
            $table->index(['distributor_id', 'status'], 'cutoff_relations_distributor_status_idx');
            $table->index(['status', 'payment_due_date'], 'cutoff_relations_status_due_date_idx');
            $table->index(['payment_reference', 'status'], 'cutoff_relations_reference_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cutoff_relations');
    }
};