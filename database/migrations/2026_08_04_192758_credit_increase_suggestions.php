<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_increase_suggestions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('distributor_id')->constrained('distributors');

            $table->unsignedTinyInteger('score')->default(0);
            $table->decimal('suggested_increase', 12, 2);
            $table->json('reason_json')->nullable();

            $table->enum('status', [
                'PENDIENTE',
                'APROBADA',
                'RECHAZADA'
            ])->default('PENDIENTE');

            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->constrained('users');

            $table->foreignId('rejected_by_user_id')
                ->nullable()
                ->constrained('users');

            $table->timestamp('decided_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('status');
            $table->index(['distributor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_increase_suggestions');
    }
};