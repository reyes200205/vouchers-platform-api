<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            // La relación que la cajera seleccionó al conciliar -- puede ser una
            // relación VENCIDA/CERRADA distinta de la que finalmente recibe el
            // pago (ver RetroactiveReconciliationService::findLiveTip): el pago
            // real siempre se aplica a la relación "viva" al final de la cadena
            // de arrastre, pero aquí se conserva cuál fue la que el usuario
            // originalmente escogió, para auditoría.
            $table->unsignedBigInteger('original_cutoff_relation_id')->nullable()->after('bank_transaction_id');

            // true cuando la relación seleccionada ya estaba VENCIDA/CERRADA (o
            // PAGADA con multa) al momento de conciliar: dispara la lógica de
            // corrección retroactiva (RetroactiveReconciliationService) en vez
            // del flujo normal al segundo autorizar (VerifyReconciliationService).
            $table->boolean('is_retroactive_correction')->default(false)->after('notes');

            // Cuánta multa se le quitó a la cadena completa al aprobar la
            // corrección (solo tiene valor si is_retroactive_correction = true y
            // la fecha real del depósito cayó dentro de la ventana "a tiempo").
            $table->decimal('waived_late_fees_total', 12, 2)->nullable()->after('is_retroactive_correction');

            $table->foreign('original_cutoff_relation_id')
                ->references('id')
                ->on('cutoff_relations');
        });
    }

    public function down(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->dropForeign(['original_cutoff_relation_id']);
            $table->dropColumn(['original_cutoff_relation_id', 'is_retroactive_correction', 'waived_late_fees_total']);
        });
    }
};
