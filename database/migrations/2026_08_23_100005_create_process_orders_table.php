<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shared textile process-order backbone. One row represents a run of a
 * configurable process (knitting, dyeing, finishing, …) that consumes input
 * materials and produces an output product/batch, optionally linked to a parent
 * manufacturing order. Reuses the existing inventory + WIP + accounting engine;
 * the cost columns capture the full production cost breakdown (populated by the
 * costing phase). Company-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('reference');
            $table->foreignId('process_type_id')->constrained('process_types')->restrictOnDelete();
            $table->foreignId('manufacturing_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('output_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('output_batch_id')->nullable()->constrained('batches')->nullOnDelete();

            $table->decimal('planned_quantity', 18, 4)->default(0);
            $table->decimal('produced_quantity', 18, 4)->default(0);
            $table->decimal('wastage_quantity', 18, 4)->default(0);
            $table->string('status')->default('draft');

            // Full production cost breakdown (populated in the costing phase).
            $table->decimal('material_cost', 15, 2)->default(0);
            $table->decimal('labour_cost', 15, 2)->default(0);
            $table->decimal('machine_cost', 15, 2)->default(0);
            $table->decimal('utility_cost', 15, 2)->default(0);
            $table->decimal('overhead_cost', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('wip_cost', 15, 2)->default(0);
            $table->decimal('output_unit_cost', 18, 4)->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'status']);
            $table->index('process_type_id');
            $table->index('manufacturing_order_id');
            $table->index('machine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_orders');
    }
};
