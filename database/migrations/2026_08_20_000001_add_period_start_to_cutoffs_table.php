<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cutoffs', function (Blueprint $table) {
            // Antes solo se guardaba scheduled_at (fin de periodo). Sin el
            // inicio no se puede reprocesar un corte ya existente (recalcular
            // sus relaciones sobre el mismo periodo) sin adivinarlo.
            $table->date('period_start')->nullable()->after('base_day_of_month');
        });
    }

    public function down(): void
    {
        Schema::table('cutoffs', function (Blueprint $table) {
            $table->dropColumn('period_start');
        });
    }
};
