<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * % de puntos extra que se otorga cuando el pago cae dentro de la ventana de
     * pago anticipado (ver GenerateCutoffService::calculateEarlyBonusPoints).
     * Antes estaba fijo en 10% directo en el codigo; se mueve aqui junto a
     * late_penalty_percentage para que un admin lo pueda ajustar sin desplegar.
     */
    public function up(): void
    {
        Schema::table('point_settings', function (Blueprint $table) {
            $table->decimal('early_bonus_percentage', 8, 4)->default(10.0000)->after('late_penalty_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('point_settings', function (Blueprint $table) {
            $table->dropColumn('early_bonus_percentage');
        });
    }
};
