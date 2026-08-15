<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla singleton de configuracion global del sistema de puntos.
     * Solo debe existir 1 fila. Editable unicamente por rol ADMIN.
     */
    public function up(): void
    {
        Schema::create('point_settings', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('point_divisor_factor')->default(1200);
            $table->unsignedInteger('point_multiplier')->default(3);
            $table->decimal('point_value_mxn', 12, 2)->default(2.00);
            $table->decimal('late_penalty_percentage', 8, 4)->default(20.0000);

            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_settings');
    }
};