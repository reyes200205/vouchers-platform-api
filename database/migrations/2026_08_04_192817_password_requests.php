<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('password_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('status', [
                'PENDIENTE',
                'APROBADA',
                'RECHAZADA',
                'EXPIRADA'
            ])->default('PENDIENTE');

            $table->string('generated_token')
                ->nullable()
                ->unique();

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_requests');
    }
};