<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery Order / Delivery Challan (spec: DO module). An operational document
 * that records goods leaving the warehouse against a Sales Order — posts stock
 * out at moving-average cost and books COGS. Invoicing (AR/Sales) is handled
 * separately via the existing sales-invoicing flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32);
            $table->foreignId('sales_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->date('delivery_date');
            $table->string('delivery_address')->nullable();
            $table->string('vehicle_no', 64)->nullable();
            $table->string('driver_name', 128)->nullable();
            $table->string('driver_phone', 32)->nullable();
            $table->string('transporter', 128)->nullable();
            $table->string('customer_reference', 128)->nullable();
            $table->string('received_by', 128)->nullable();
            $table->string('status', 16)->default('draft');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};
