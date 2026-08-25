<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cutoffs', function (Blueprint $table) {
            // Respaldo a nivel de base de datos contra dos cortes para el
            // mismo periodo en la misma sucursal (GenerateCutoffService ya
            // valida esto antes de insertar, pero esa validación por sí sola
            // no evita una condición de carrera: dos solicitudes casi
            // simultáneas -- doble clic, reintento de red -- podrían leer el
            // mismo "último corte" antes de que ninguna termine de guardar y
            // las dos pasarían la validación). period_start es nullable
            // (cortes viejos, de antes de esa columna) así que varias filas
            // en null no chocan entre sí -- eso es intencional.
            $table->unique(['branch_id', 'period_start'], 'cutoffs_branch_period_start_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cutoffs', function (Blueprint $table) {
            $table->dropUnique('cutoffs_branch_period_start_unique');
        });
    }
};
