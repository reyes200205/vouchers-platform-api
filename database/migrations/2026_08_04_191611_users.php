<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('person_id');
            $table->string('username', 80)->unique();
            $table->string('password_hash', 255);
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_vpn')->default(false);
            $table->enum('login_channel', ['WEB', 'VPN_WEB', 'MOVIL'])->default('WEB');
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('person_id')->references('id')->on('people');
            $table->unique('person_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
