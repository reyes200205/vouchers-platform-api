<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->unsignedBigInteger('verified_by_user_id')->nullable()->after('reconciled_by_user_id');
            $table->timestamp('verified_at')->nullable()->after('verified_by_user_id');

            $table->foreign('verified_by_user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->dropForeign(['verified_by_user_id']);
            $table->dropColumn(['verified_by_user_id', 'verified_at']);
        });
    }
};