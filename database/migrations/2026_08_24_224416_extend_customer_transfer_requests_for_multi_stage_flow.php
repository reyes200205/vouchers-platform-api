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
        DB::statement("ALTER TABLE customer_transfer_requests MODIFY status ENUM(
            'PENDIENTE_DESTINO',
            'RECHAZADA_DESTINO',
            'PENDIENTE_COORDINADOR',
            'RECHAZADA_COORDINADOR',
            'AUTORIZADA',
            'EJECUTADA',
            'CANCELADA'
        ) NOT NULL DEFAULT 'PENDIENTE_DESTINO'");

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

        DB::statement("ALTER TABLE customer_transfer_requests MODIFY status ENUM(
            'PENDIENTE_COORDINADOR',
            'APROBADA',
            'RECHAZADA',
            'CANCELADA',
            'EJECUTADA'
        ) NOT NULL DEFAULT 'PENDIENTE_COORDINADOR'");
    }
};
