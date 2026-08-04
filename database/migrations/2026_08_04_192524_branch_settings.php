<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();

            $table->unsignedTinyInteger('cutoff_day')->nullable();
            $table->time('cutoff_time')->nullable();

            $table->unsignedSmallInteger('payment_frequency_days')->default(14);
            $table->unsignedSmallInteger('payment_due_days')->default(15);
            $table->decimal('default_credit_limit', 12, 2)->default(0.00);

            $table->json('insurance_rates_json')->nullable();
            $table->decimal('opening_commission_percentage', 6, 4)->default(10.0000);
            $table->decimal('biweekly_interest_percentage', 6, 4)->default(5.0000);
            $table->decimal('late_payment_penalty_amount', 12, 2)->default(300.00);
            $table->decimal('auto_increase_threshold', 12, 2)->nullable();
            $table->decimal('minimum_score_increase_percentage', 5, 2)->default(70.00);

            // Overrides por sucursal para no tocar catalogos globales.
            $table->json('category_settings_json')->nullable();
            $table->json('financial_product_settings_json')->nullable();

            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_settings');
    }
};