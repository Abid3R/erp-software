<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §9 & §15 — roll-level tracking for roll-tracked fabric products. Each roll belongs
 * to an output batch and the process order that produced it, and carries its own
 * weight/length/GSM/width/shade and QC status. Rolls are a *sub-division* of a batch,
 * not a parallel inventory: batch quantity remains the stock of record. Company-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabric_rolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('roll_number');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('process_order_id')->nullable()->constrained('process_orders')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('weight', 18, 4)->default(0);
            $table->decimal('length', 18, 4)->nullable();
            $table->string('gsm')->nullable();
            $table->string('width')->nullable();
            $table->string('shade')->nullable();
            $table->string('qc_status')->default('pending');   // pending/passed/failed
            $table->string('status')->default('available');    // available/rejected/consumed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'roll_number']);
            $table->index(['company_id', 'status']);
            $table->index('batch_id');
            $table->index('process_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_rolls');
    }
};
