<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Cuantas quincenas ya se le FACTURARON a este vale (se generó su
            // item en un corte), sin importar si esa quincena ya se pagó o
            // sigue arrastrándose como deuda vencida. Es lo que determina el
            // "installment_number" (quincena X/N) del próximo item que se
            // genere en GenerateCutoffService — a propósito distinto de
            // "payments_made", que cuenta cuántas quincenas la distribuidora
            // ya LIQUIDÓ con la sucursal (eso puede ir atrasado respecto a
            // esto: el calendario de facturación no espera a que se pague).
            $table->unsignedSmallInteger('installments_billed')
                ->default(0)
                ->after('payments_made');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('installments_billed');
        });
    }
};
