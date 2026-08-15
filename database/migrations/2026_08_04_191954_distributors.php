<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('person_id')->unique();
            $table->unsignedBigInteger('application_id')->nullable()->unique();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('coordinator_user_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('distributor_number', 50)->unique();

            $table->enum('status', [ 'CANDIDATA', 'POSIBLE', 'ACTIVA', 'INACTIVA', 'MOROSA', 'BLOQUEADA', 'CERRADA' ])->default('CANDIDATA');

            $table->decimal('credit_limit', 12, 2)->default(0.00);
            $table->decimal('available_credit', 12, 2)->default(0.00);
            $table->boolean('unlimited_credit')->default(false);
            $table->decimal('current_points', 12, 2)->default(0.00);
            $table->boolean('can_issue_vouchers')->default(false);
            $table->boolean('is_external')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('person_id')->references('id')->on('people');
            $table->foreign('application_id')->references('id')->on('applications');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('coordinator_user_id')->references('id')->on('users');
            $table->foreign('category_id')->references('id')->on('distributor_categories');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts');

            $table->index('status');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributors');
    }
};