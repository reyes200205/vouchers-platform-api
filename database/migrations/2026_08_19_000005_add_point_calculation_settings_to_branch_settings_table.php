<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La fórmula de puntos (total del corte / divisor, piso, * multiplicador
     * para pagos anticipados; -% cuando llega fuera de tiempo) la puede
     * ajustar cada sucursal, igual que sus demás reglas de negocio
     * (payment_due_days, late_payment_penalty_amount, etc.). point_settings
     * (tabla global) se deja como el default del sistema cuando una sucursal
     * no configuró su propio valor — ver SettleCutoffRelationService.
     */
    public function up(): void
    {
        Schema::table('branch_settings', function (Blueprint $table) {
            $table->unsignedInteger('point_divisor_factor')->nullable()->after('point_value_mxn');
            $table->unsignedInteger('point_multiplier')->nullable()->after('point_divisor_factor');
            $table->decimal('late_penalty_percentage', 8, 4)->nullable()->after('point_multiplier');
        });
    }

    public function down(): void
    {
        Schema::table('branch_settings', function (Blueprint $table) {
            $table->dropColumn(['point_divisor_factor', 'point_multiplier', 'late_penalty_percentage']);
        });
    }
};
