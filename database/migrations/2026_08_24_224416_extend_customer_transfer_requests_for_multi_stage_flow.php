<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Schema::table()->enum(...)->change() en vez de un ALTER TABLE ...
        // MODIFY crudo: ese SQL es sintaxis exclusiva de MySQL/MariaDB y
        // truena con "syntax error" en SQLite (la conexion que usan las
        // pruebas automatizadas via RefreshDatabase) -- el builder de
        // Schema si sabe generar el DDL correcto para cada motor.
        Schema::table('customer_transfer_requests', function (Blueprint $table) {
            $table->enum('status', [
                'PENDIENTE_DESTINO',
                'RECHAZADA_DESTINO',
                'PENDIENTE_COORDINADOR',
                'RECHAZADA_COORDINADOR',
                'AUTORIZADA',
                'EJECUTADA',
                'CANCELADA',
            ])->default('PENDIENTE_DESTINO')->change();
        });

        Schema::table('customer_transfer_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('destination_decided_by_user_id')->nullable()->after('requested_by_user_id');
            $table->timestamp('destination_decided_at')->nullable()->after('destination_decided_by_user_id');
            $table->timestamp('coordinator_decided_at')->nullable()->after('coordinator_user_id');
            $table->unsignedBigInteger('finalized_by_user_id')->nullable()->after('coordinator_decided_at');

            $table->foreign('destination_decided_by_user_id', 'ctr_destination_decided_by_fk')->references('id')->on('users');
            $table->foreign('finalized_by_user_id', 'ctr_finalized_by_fk')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_transfer_requests', function (Blueprint $table) {
            $table->dropForeign('ctr_destination_decided_by_fk');
            $table->dropForeign('ctr_finalized_by_fk');
            $table->dropColumn([
                'destination_decided_by_user_id',
                'destination_decided_at',
                'coordinator_decided_at',
                'finalized_by_user_id',
            ]);
        });

        Schema::table('customer_transfer_requests', function (Blueprint $table) {
            $table->enum('status', [
                'PENDIENTE_COORDINADOR',
                'APROBADA',
                'RECHAZADA',
                'CANCELADA',
                'EJECUTADA',
            ])->default('PENDIENTE_COORDINADOR')->change();
        });
    }
};
