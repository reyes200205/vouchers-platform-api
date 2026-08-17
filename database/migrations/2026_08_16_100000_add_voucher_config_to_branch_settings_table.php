<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('voucher_amount_step')->default(100);
            $table->decimal('pre_vale_max_percentage', 5, 2)->default(50.00);
            $table->decimal('pre_vale_tolerance_amount', 12, 2)->default(500.00);
            $table->decimal('point_value_mxn', 12, 2)->default(2.00);
        });
    }

    public function down(): void
    {
        Schema::table('branch_settings', function (Blueprint $table) {
            $table->dropColumn([
                'voucher_amount_step',
                'pre_vale_max_percentage',
                'pre_vale_tolerance_amount',
                'point_value_mxn',
            ]);
        });
    }
};