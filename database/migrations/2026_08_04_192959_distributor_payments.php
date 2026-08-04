<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributor_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')
                ->nullable()
                ->after('distributor_id');

            $table->foreign('item_id')
                ->references('id')
                ->on('cutoff_relation_items')
                ->onDelete('cascade');

            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('distributor_payments', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropColumn('item_id');
        });
    }
};