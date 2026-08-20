<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda la fecha de vencimiento de la quincena que estaba vigente en el
     * vale AL MOMENTO de registrar este pago.
     *
     * Es necesaria porque `vouchers.payment_due_date` se actualiza (avanza 15
     * dias) inmediatamente despues de cada pago, para reflejar la siguiente
     * quincena. Si el corte (GenerateCutoffService) volviera a comparar la
     * fecha de un pago pasado contra `vouchers.payment_due_date` actual,
     * estaria comparando contra la fecha de la quincena SIGUIENTE, no contra
     * la que realmente aplicaba cuando se hizo el pago, dando pagos atrasados
     * o anticipados como si fueran a tiempo. Esta columna es el snapshot
     * inmutable que evita ese desfase.
     */
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->date('due_date_snapshot')->nullable()->after('payment_date');
        });
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropColumn('due_date_snapshot');
        });
    }
};
