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
        Schema::table('applications', function (Blueprint $table) {
            // Historial de correcciones que el verificador hizo a los datos
            // capturados por el coordinador (ver UpdateApplicationService).
            // Se muestra en el detalle de la solicitud para que gerencia vea
            // exactamente qué cambió antes de decidir.
            $table->json('verifier_corrections_json')->nullable()->after('rejection_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('verifier_corrections_json');
        });
    }
};
