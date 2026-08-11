<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A production order: make `quantity` of a product (per its BOM) at a warehouse.
 * On completion, components are issued from stock and finished goods received at
 * the rolled-up component cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturing_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->foreignId('bill_of_materials_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();  // finished good
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 19, 4);
            $table->string('status', 16)->default('draft');
            $table->decimal('output_unit_cost', 19, 4)->nullable();  // set on completion
            $table->decimal('total_cost', 19, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturing_orders');
    }
};
