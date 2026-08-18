<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distributor_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('id');

            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            $table->dropUnique('distributor_categories_code_unique');
            $table->dropUnique('distributor_categories_name_unique');

            $table->unique(['branch_id', 'code'], 'distributor_categories_branch_code_unique');
            $table->unique(['branch_id', 'name'], 'distributor_categories_branch_name_unique');

            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('distributor_categories', function (Blueprint $table) {
            $table->dropUnique('distributor_categories_branch_code_unique');
            $table->dropUnique('distributor_categories_branch_name_unique');
            $table->dropIndex(['branch_id']);

            $table->unique('code', 'distributor_categories_code_unique');
            $table->unique('name', 'distributor_categories_name_unique');

            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
