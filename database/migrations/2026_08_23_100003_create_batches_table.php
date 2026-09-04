<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch / lot records for traceability across the textile chain (purchase → yarn
 * batch → grey fabric batch → dyed fabric batch → finished batch → sale). Each
 * batch belongs to a product and optionally records where it originated
 * (source morph: goods receipt, process order, …). Company-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 18, 4)->default(0);        // original produced/received qty
            $table->string('status')->default('open');             // open, consumed, on_hold, rejected
            $table->nullableMorphs('source');                      // e.g. GoodsReceipt, ProcessOrder
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'batch_number']);
            $table->index(['company_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
