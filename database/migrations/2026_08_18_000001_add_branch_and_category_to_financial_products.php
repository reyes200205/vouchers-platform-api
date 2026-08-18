<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_products', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('id');
            $table->unsignedBigInteger('category_id')->nullable()->after('branch_id');

            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('distributor_categories')->nullOnDelete();

            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('financial_products', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['category_id']);
            $table->dropIndex(['branch_id']);
            $table->dropColumn(['branch_id', 'category_id']);
        });
    }
};
