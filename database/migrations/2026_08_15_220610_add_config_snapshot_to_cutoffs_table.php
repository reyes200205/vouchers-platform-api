<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cutoffs', function (Blueprint $table) {
            $table->json('config_snapshot_json')->nullable()
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('cutoffs', function (Blueprint $table) {
            $table->dropColumn('config_snapshot_json');
        });
    }
};