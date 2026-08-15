<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_transfer_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('source_distributor_id');
            $table->unsignedBigInteger('destination_distributor_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('coordinator_user_id')->nullable();

            $table->enum('status', [
                'PENDIENTE_COORDINADOR',
                'APROBADA',
                'RECHAZADA',
                'CANCELADA',
                'EJECUTADA',
            ])->default('PENDIENTE_COORDINADOR');

            $table->timestamp('executed_at')->nullable();

            $table->text('request_reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('comments')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('source_distributor_id')->references('id')->on('distributors');
            $table->foreign('destination_distributor_id')->references('id')->on('distributors');
            $table->foreign('requested_by_user_id')->references('id')->on('users');
            $table->foreign('coordinator_user_id')->references('id')->on('users');

            $table->index(['destination_distributor_id', 'status'], 'customer_transfer_destination_status_idx');
            $table->index(['source_distributor_id', 'status'], 'customer_transfer_source_status_idx');
            $table->index(['customer_id', 'status'], 'customer_transfer_customer_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_transfer_requests');
    }
};