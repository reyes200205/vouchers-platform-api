<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_settings_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_setting_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('updated_by_user_id')->nullable();

            $table->enum('event_type', [
                'SUCURSAL',
                'CATEGORIA',
                'PRODUCTO'
            ]);

            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('before_changes_json')->nullable();
            $table->json('after_changes_json')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('branch_setting_id', 'fk_bsl_setting')
                ->references('id')
                ->on('branch_settings')
                ->cascadeOnDelete();

            $table->foreign('branch_id', 'fk_bsl_branch')
                ->references('id')
                ->on('branches')
                ->cascadeOnDelete();

            $table->foreign('updated_by_user_id', 'fk_bsl_user')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['branch_id', 'created_at']);
            $table->index(['event_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_settings_logs');
    }
};