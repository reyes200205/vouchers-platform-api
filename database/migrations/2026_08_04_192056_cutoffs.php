<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cutoffs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id');
            $table->enum('cutoff_type', ['PAGOS', 'PUNTOS', 'MIXTO'])->default('PAGOS');
            $table->integer('base_day_of_month')->nullable();
            $table->time('base_time')->nullable();
            $table->dateTime('scheduled_at');
            $table->dateTime('executed_at')->nullable();
            $table->boolean('keep_date_on_holiday')->default(true);
            $table->enum('status', ['PROGRAMADO', 'EJECUTADO', 'CERRADO', 'REPROCESADO'])->default('PROGRAMADO');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('branch_id')->references('id')->on('branches');

            $table->index('branch_id');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cutoffs');
    }
};