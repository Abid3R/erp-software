<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Input (consumed) lines of a process order — the yarn, dyes, chemicals, etc.
 * issued into the process. Planned vs consumed quantities support partial runs;
 * batch_id records the specific lot consumed for traceability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_order_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('process_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->decimal('planned_quantity', 18, 4)->default(0);
            $table->decimal('consumed_quantity', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->timestamps();

            $table->index('process_order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_order_inputs');
    }
};
