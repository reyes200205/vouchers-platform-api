<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('person_id');
            $table->unsignedBigInteger('verified_by_user_id')->nullable()->after('status');
            $table->timestamp('verified_at')->nullable()->after('verified_by_user_id');

            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('verified_by_user_id')->references('id')->on('users');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['verified_by_user_id']);
            $table->dropColumn(['branch_id', 'verified_by_user_id', 'verified_at']);
        });
    }
};