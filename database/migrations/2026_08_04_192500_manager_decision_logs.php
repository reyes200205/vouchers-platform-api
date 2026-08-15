<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_decision_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('manager_user_id');
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('distributor_id')->nullable();

            $table->enum('event_type', [
                'NUEVA_DISTRIBUIDORA',
                'INCREMENTO_LIMITE',
                'INCREMENTO_MANUAL',
                'INCREMENTO_SUGERIDO_APROBADO',
                'APROBACION',
                'RECHAZO'
            ]);

            $table->decimal('previous_amount', 12, 2)->default(0);
            $table->decimal('new_amount', 12, 2)->default(0);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('manager_user_id')->references('id')->on('users');
            $table->foreign('application_id')->references('id')->on('applications');
            $table->foreign('distributor_id')->references('id')->on('distributors');

            $table->index('manager_user_id');
            $table->index('application_id');
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_decision_logs');
    }
};