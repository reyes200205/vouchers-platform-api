<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('person_id')->unique();
            $table->string('customer_code', 50)->nullable()->unique();
            $table->enum('status', ['EN_VERIFICACION', 'ACTIVO', 'BLOQUEADO', 'MOROSO', 'INACTIVO'])->default('EN_VERIFICACION')->nullable();
            $table->text('notes')->nullable();
            $table->string('id_front_photo')->nullable();
            $table->string('id_back_photo')->nullable();
            $table->string('id_selfie_photo')->nullable();
            $table->string('proof_of_address_photo')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_clabe', 18)->nullable();
            $table->string('account_holder_name')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('person_id')->references('id')->on('people');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};