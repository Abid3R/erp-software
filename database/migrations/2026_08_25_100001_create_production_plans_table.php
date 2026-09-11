<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master production schedule (Time & Action plan). One row per customer order (or
 * ad-hoc plan) that will be produced through a sequence of textile stages. It
 * carries the target quantity and dates; the actual runs are the process orders
 * spawned from its stages. Purely a planning entity — no stock or accounting
 * effect. Company-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('reference');
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete(); // main finished good
            $table->decimal('planned_quantity', 18, 4)->default(0);
            $table->string('unit')->nullable();          // e.g. KG, PCS
            $table->date('plan_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();         // customer delivery target
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'status']);
            $table->index('sales_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_plans');
    }
};
