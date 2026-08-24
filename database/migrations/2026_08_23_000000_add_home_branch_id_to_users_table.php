<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Sucursal "base" del gerente general (solo informativo/organizativo).
            // A diferencia de model_has_roles.branch_id, esta columna NO limita el
            // alcance de sus permisos: un gerente general siempre tiene acceso
            // global sin importar el valor de home_branch_id.
            $table->foreignId('home_branch_id')->nullable()->after('person_id')->constrained('branches')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('home_branch_id');
        });
    }
};
