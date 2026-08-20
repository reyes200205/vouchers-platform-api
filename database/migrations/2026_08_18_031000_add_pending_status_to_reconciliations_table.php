<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->enum('status', [
                'PENDIENTE_VERIFICACION',
                'CONCILIADA',
                'CON_DIFERENCIA',
                'RECHAZADA',
            ])->default('CONCILIADA')->change();
        });
    }

    public function down(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->enum('status', [
                'CONCILIADA',
                'CON_DIFERENCIA',
                'RECHAZADA',
            ])->default('CONCILIADA')->change();
        });
    }
};
