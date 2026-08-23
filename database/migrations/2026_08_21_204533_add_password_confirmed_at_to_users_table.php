<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_confirmed_at')->nullable()->after('last_login_at');
        });

        // Los usuarios que ya existian antes de este cambio ya venian usando su
        // password de siempre (no una temporal por activar) -- no se les debe
        // pedir el modal de "confirma o cambia tu contrasena" en su siguiente
        // login. Solo las cuentas nuevas (creadas con NULL) lo requieren.
        DB::table('users')->whereNull('password_confirmed_at')->update(['password_confirmed_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_confirmed_at');
        });
    }
};
