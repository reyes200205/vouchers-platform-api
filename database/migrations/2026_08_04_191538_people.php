<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('second_last_name', 100)->nullable();
            $table->enum('gender', ['M', 'F', 'OTHER'])->nullable();
            $table->date('birth_date')->nullable();
            $table->string('curp', 18)->nullable()->unique();
            $table->string('rfc', 13)->nullable()->unique();
            $table->string('home_phone', 30)->nullable();
            $table->string('mobile_phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('street', 150)->nullable();
            $table->string('external_number', 30)->nullable();
            $table->string('neighborhood', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['last_name', 'second_last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};