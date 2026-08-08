<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Derived, lockable projection of the inventory ledger: current on-hand quantity
 * and moving average cost per product per warehouse. Rebuildable from the ledger
 * at any time; exists for O(1) availability checks and row-level locking under
 * concurrency (spec #17). The ledger remains the source of truth (spec #65).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 19, 4)->default(0);
            $table->decimal('average_cost', 19, 6)->default(0);
            $table->decimal('reserved_quantity', 19, 4)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'product_id']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
