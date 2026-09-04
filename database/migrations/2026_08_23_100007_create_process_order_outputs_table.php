<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Output (produced) lines of a process order. Most runs have a single primary
 * output (grey fabric, dyed fabric, …) but the table supports co-/by-products.
 * Each output carries its own produced batch for downstream traceability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_order_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('process_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->index('process_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_order_outputs');
    }
};
