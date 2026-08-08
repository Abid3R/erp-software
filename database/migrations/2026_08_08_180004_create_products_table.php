<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products. Prices are NUMERIC (never float — spec #52). cost_price here is the
 * default/last purchase price for reference only; the authoritative inventory
 * cost is the moving average maintained on the stock ledger (see INVENTORY.md).
 * A product declares its stock (base) unit and optional purchase/sales units
 * (spec #19). Batch/serial tracking is opt-in per product (spec #18).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->constrained();          // stock / inventory unit
            $table->foreignId('purchase_unit_id')->nullable()->constrained('units');
            $table->foreignId('sales_unit_id')->nullable()->constrained('units');
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('cost_price', 19, 2)->default(0);     // reference cost
            $table->decimal('selling_price', 19, 2)->default(0);
            $table->boolean('tracks_batch')->default(false);
            $table->boolean('tracks_serial')->default(false);
            $table->decimal('reorder_level', 19, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
