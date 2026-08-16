<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->boolean('is_pre_vale')->default(false)->after('status');
            $table->string('authorized_number', 50)->nullable()->after('transfer_reference');
            $table->foreignId('disbursed_by_user_id')->nullable()->after('approved_by_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('voucher_request_id')->nullable()->after('financial_product_id')->constrained('voucher_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voucher_request_id');
            $table->dropConstrainedForeignId('disbursed_by_user_id');
            $table->dropColumn(['is_pre_vale', 'authorized_number']);
        });
    }
};