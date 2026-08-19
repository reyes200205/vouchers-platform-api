<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La multa por atraso ("late_fee_amount") dejo de configurarse por producto
     * financiero: ahora vive unicamente en branch_settings.late_payment_penalty_amount,
     * global por sucursal. RequestVoucherService ya toma el valor de ahi al armar
     * el snapshot del vale (vouchers.late_fee_amount_snapshot sigue existiendo,
     * solo cambia de donde se copia).
     */
    public function up(): void
    {
        Schema::table('financial_products', function (Blueprint $table) {
            $table->dropColumn('late_fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('financial_products', function (Blueprint $table) {
            $table->decimal('late_fee_amount', 12, 2)->default(0.00);
        });
    }
};
