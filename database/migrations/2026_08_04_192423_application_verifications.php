<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_verifications', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('application_id')
                ->constrained('applications')
                ->onDelete('cascade');

            $table->foreignId('verifier_user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Resultado de la verificación
            $table->enum('result', [
                'PENDIENTE',
                'VERIFICADA',
                'RECHAZADA'
            ])->default('PENDIENTE');

            // Observaciones del verificador
            $table->text('notes')->nullable();

            // Ubicación GPS donde se realizó la verificación
            $table->decimal('verification_latitude', 10, 7)->nullable();
            $table->decimal('verification_longitude', 11, 8)->nullable();

            // Fecha y hora de la visita
            $table->dateTime('visit_date')->nullable();

            // Checklist de validación (JSON)
            $table->longText('checklist_json')->nullable();
            $table->longText('justifications_json')->nullable();

            // Evidencia fotográfica
            $table->string('front_photo')->nullable();
            $table->string('id_with_person_photo')->nullable();
            $table->string('proof_of_address_photo')->nullable();
            $table->longText('additional_evidence_json')->nullable();

            // Distancia calculada entre el domicilio y la verificación
            $table->decimal('distance_meters', 10, 2)->nullable();

            // Timestamps manuales (como en el resto de tus tablas)
            $table->dateTime('created_at')
                ->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->dateTime('updated_at')
                ->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));

            // Índices y únicos
            $table->unique('application_id');
            $table->index('verifier_user_id');
            $table->index('result');
            $table->index('visit_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_verifications');
    }
};