<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buying prices (spec: Purchasing → Buying Prices): a supplier's agreed unit price
 * per product, used to pre-fill purchase-order lines. One active price per
 * supplier/product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_price', 19, 4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'supplier_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_prices');
    }
};
