<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_score_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('distributor_id')->constrained('distributors');

            $table->string('evaluation_month', 7);
            $table->unsignedTinyInteger('base_score')->default(100);
            $table->decimal('final_score', 5, 2)->default(100.00);
            $table->json('factors_json')->nullable();
            $table->decimal('suggested_increase', 12, 2)->default(0.00);
            $table->boolean('auto_applied')->default(false);

            $table->timestamps();

            $table->unique(['distributor_id', 'evaluation_month']);
            $table->index('evaluation_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_score_history');
    }
};