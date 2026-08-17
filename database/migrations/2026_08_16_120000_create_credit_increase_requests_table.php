<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_increase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->constrained('distributors')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users');

            $table->decimal('requested_amount', 12, 2);
            $table->string('reason', 255)->nullable();

            $table->string('status', 20)->default('PENDIENTE');
            $table->decimal('pre_authorized_amount', 12, 2)->nullable();
            $table->foreignId('pre_authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('pre_authorized_at')->nullable();

            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision_notes', 255)->nullable();
            $table->dateTime('decided_at')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['distributor_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_increase_requests');
    }
};