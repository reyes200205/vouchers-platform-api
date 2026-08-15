<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transaction_imports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('filename', 255);
            $table->string('file_hash', 64);
            $table->unsignedBigInteger('imported_by_user_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('errors_json')->nullable();
            $table->enum('status', ['COMPLETADO', 'PARCIAL', 'ERROR'])->default('COMPLETADO');
            $table->timestamp('imported_at')->useCurrent();

            $table->foreign('imported_by_user_id')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches');

            $table->unique('file_hash');
            $table->index('branch_id');
            $table->index('imported_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transaction_imports');
    }
};