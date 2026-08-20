<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La regla de negocio real de puntos no tiene un bono extra por porcentaje:
     * "Solo para pagos anticipados: total / 1200 (piso) * 3 = puntos. Pagos
     * fuera de tiempo: se elimina el 20% del total." No hay un tercer nivel de
     * bonificación — esta columna se agregó por error para un bono que nunca
     * se pidió y ya no se usa en ningún lado (ver SettleCutoffRelationService).
     */
    public function up(): void
    {
        Schema::table('point_settings', function (Blueprint $table) {
            $table->dropColumn('early_bonus_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('point_settings', function (Blueprint $table) {
            $table->decimal('early_bonus_percentage', 8, 4)->default(10.0000)->after('late_penalty_percentage');
        });
    }
};
