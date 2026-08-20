<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reversa de 2026_08_19_000003_drop_late_fee_amount_from_financial_products_table:
     * no todos los productos de una sucursal deben tener la misma multa por atraso
     * (ni el mismo interes quincenal, ni el mismo seguro), asi que la multa vuelve
     * a vivir por producto financiero. branch_settings.late_payment_penalty_amount
     * se queda solo como el valor por defecto que se copia al crear un producto
     * nuevo si no se manda uno explicito (ver BranchManager\ProductController::store()).
     */
    public function up(): void
    {
        Schema::table('financial_products', function (Blueprint $table) {
            $table->decimal('late_fee_amount', 12, 2)->default(0.00)->after('fortnightly_interest_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('financial_products', function (Blueprint $table) {
            $table->dropColumn('late_fee_amount');
        });
    }
};
