<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->decimal('principal_amount', 12, 2)->default(0.00);
            $table->integer('number_of_fortnights');
            $table->decimal('company_commission_percentage', 8, 4)->default(0.0000);
            $table->decimal('insurance_amount', 12, 2)->default(0.00);
            $table->decimal('fortnightly_interest_percentage', 8, 4)->default(0.0000);
            $table->decimal('late_fee_amount', 12, 2)->default(0.00);
            $table->enum('disbursement_method', ['TRANSFERENCIA', 'EFECTIVO', 'MIXTO'])->default('TRANSFERENCIA');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_products');
    }
};