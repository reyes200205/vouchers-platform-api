<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('voucher_expiration_days')->nullable()->default(5)->after('point_value_mxn');
        });
    }

    public function down(): void
    {
        Schema::table('branch_settings', function (Blueprint $table) {
            $table->dropColumn('voucher_expiration_days');
        });
    }
};
