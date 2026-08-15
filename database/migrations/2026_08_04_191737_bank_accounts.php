<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('owner_type', ['PERSONA', 'DISTRIBUIDORA', 'EMPRESA']);
            $table->unsignedBigInteger('owner_id');
            $table->string('bank', 100);
            $table->string('account_holder_name', 150);
            $table->string('masked_account_number', 50)->nullable();
            $table->string('clabe', 30)->nullable();
            $table->string('agreement', 50)->nullable();
            $table->string('reference_base', 100)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};