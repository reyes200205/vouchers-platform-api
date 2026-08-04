<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('applicant_person_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('captured_by_user_id')->nullable();
            $table->unsignedBigInteger('coordinator_user_id')->nullable();
            $table->unsignedBigInteger('assigned_verifier_id')->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();

            $table->enum('status', [ 'PRE', 'MODIFICADA', 'EN_REVISION', 'VERIFICADA', 'POSIBLE_DISTRIBUIDORA', 'APROBADA', 'RECHAZADA' ])->default('PRE');

            $table->string('initial_category_code', 20)->default('COPPER');
            $table->longText('family_data_json')->nullable();
            $table->longText('external_affiliations_json')->nullable();
            $table->longText('vehicles_json')->nullable();
            $table->decimal('requested_credit_limit', 12, 2)->nullable();
            $table->string('id_front_path')->nullable();
            $table->string('id_back_path')->nullable();
            $table->string('proof_of_address_path')->nullable();
            $table->string('credit_bureau_report_path')->nullable();
            $table->string('credit_bureau_result', 100)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('prevale_approved')->default(false);
            $table->boolean('house_photos_complete')->default(false);
            $table->timestamp('taken_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('applicant_person_id')->references('id')->on('people');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('captured_by_user_id')->references('id')->on('users');
            $table->foreign('coordinator_user_id')->references('id')->on('users');
            $table->foreign('assigned_verifier_id')->references('id')->on('users');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts');

            $table->index('status');
            $table->index('branch_id');
            $table->index('assigned_verifier_id');
            $table->index('initial_category_code');
            $table->index(['branch_id', 'status'], 'applications_branch_status_idx');
            $table->index(['coordinator_user_id', 'status'], 'applications_coordinator_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};