<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cutoff_relations', function (Blueprint $table) {

            $table->unsignedBigInteger('previous_relation_id')
                ->nullable()
                ->after('distributor_id');

            $table->timestamp('closed_by_carryover_at')
                ->nullable()
                ->after('status');

            $table->decimal('total_carryover_received', 12, 2)
                ->default(0.00)
                ->after('total_amount_due');


            $table->foreign('previous_relation_id')
                ->references('id')
                ->on('cutoff_relations')
                ->nullOnDelete();


            $table->index('previous_relation_id');
        });
    }


    public function down(): void
    {
        Schema::table('cutoff_relations', function (Blueprint $table) {

            $table->dropForeign(['previous_relation_id']);

            $table->dropIndex(['previous_relation_id']);

            $table->dropColumn([
                'previous_relation_id',
                'closed_by_carryover_at',
                'total_carryover_received',
            ]);
        });
    }
};