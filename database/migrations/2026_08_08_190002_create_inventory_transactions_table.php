<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The authoritative, append-only inventory ledger (spec #14, #65). Every stock
 * movement is one row; on-hand quantity and valuation derive from it. Quantity is
 * signed (+ inbound, - outbound). Historical rows are never mutated (a DB trigger
 * enforcing immutability is added in the security-hardening phase, mirroring the
 * accounting ledger).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);                       // InventoryTransactionType
            $table->nullableMorphs('reference');              // source document
            $table->decimal('quantity', 19, 4);               // signed
            $table->decimal('unit_cost', 19, 6);              // valuation cost of this move
            $table->decimal('total_cost', 19, 2);             // |quantity| * unit_cost
            $table->decimal('balance_after', 19, 4);          // running on-hand after this move
            $table->decimal('average_cost_after', 19, 6);     // moving average after this move
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'warehouse_id']);
            $table->index('company_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
