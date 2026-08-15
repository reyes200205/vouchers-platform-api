<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_distributor', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('distributor_id');
            $table->unsignedBigInteger('customer_id');
            $table->enum('relationship_status', ['ACTIVA', 'BLOQUEADA', 'TERMINADA'])->default('ACTIVA');
            $table->boolean('prevale_approved')->default(false);
            $table->boolean('blocked_due_to_relationship')->default(false);
            $table->text('relationship_notes')->nullable();
            $table->timestamp('linked_at')->useCurrent();
            $table->timestamp('unlinked_at')->nullable();

            $table->foreign('distributor_id')->references('id')->on('distributors');
            $table->foreign('customer_id')->references('id')->on('customers');

            $table->unique(['distributor_id', 'customer_id']);
            $table->index('customer_id');
            $table->index(
                ['distributor_id', 'relationship_status'],
                'customer_distributor_status_idx'
            );
            $table->index(
                ['distributor_id', 'blocked_due_to_relationship'],
                'customer_distributor_relationship_idx'
            );
            $table->index('relationship_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_distributor');
    }
};