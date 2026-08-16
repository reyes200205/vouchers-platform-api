<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_redemptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('distributor_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->decimal('points', 12, 2);
            $table->decimal('point_value_snapshot', 12, 2)->default(2.00);
            $table->decimal('amount_mxn', 12, 2);
            $table->enum('status', ['PENDIENTE', 'APROBADO', 'RECHAZADO', 'CANCELADO'])->default('PENDIENTE');
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('distributor_id')->references('id')->on('distributors');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('requested_by_user_id')->references('id')->on('users');
            $table->foreign('decided_by_user_id')->references('id')->on('users');

            $table->index('distributor_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_redemptions');
    }
};