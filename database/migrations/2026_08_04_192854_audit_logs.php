<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->string('event_type', 50);
            $table->string('level', 20)->default('INFO');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_role')->nullable();

            $table->unsignedBigInteger('branch_id')->nullable();

            $table->string('module', 100);
            $table->text('description');

            $table->json('extra_data')->nullable();

            $table->text('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_type', 'created_at']);
            $table->index(['module', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['level', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};