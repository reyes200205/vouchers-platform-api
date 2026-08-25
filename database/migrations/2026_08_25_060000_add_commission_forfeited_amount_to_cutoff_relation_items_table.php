<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cutoff_relation_items', function (Blueprint $table) {
            // Acumula cuanta comision perdio la distribuidora por el atraso de
            // este item -- a diferencia de late_fee_amount (la multa, que
            // tambien se acumula cada corte que pasa sin pagarse, pero solo
            // para la quincena FINAL del vale -- ver MarkOverdueRelationsService),
            // esto ya no se calcula al vuelo en el recurso de la API (antes era
            // siempre voucher.distributor_profit_amount / total_fortnights,
            // fijo) porque debe ir creciendo junto con la multa cada vez que
            // esa misma ultima quincena se vuelve a vencer sin pagarse.
            $table->decimal('commission_forfeited_amount', 12, 2)->default(0.00)->after('late_fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('cutoff_relation_items', function (Blueprint $table) {
            $table->dropColumn('commission_forfeited_amount');
        });
    }
};
