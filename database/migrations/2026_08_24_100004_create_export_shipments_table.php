<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Export Shipment (spec: Export). The physical export event — vessel/flight,
 * container, BL/AWB — that ties together the order, PI, LC, commercial invoice,
 * packing list and delivery order. Operational only; no ledger or stock impact of
 * its own (stock left via the Delivery Order).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32);
            $table->date('shipment_date')->nullable();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('proforma_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('letter_of_credit_id')->nullable()->constrained('letters_of_credit')->nullOnDelete();
            $table->foreignId('commercial_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('port_of_loading', 128)->nullable();
            $table->string('port_of_discharge', 128)->nullable();
            $table->string('vessel_flight', 128)->nullable();
            $table->string('container_no', 64)->nullable();
            $table->string('seal_no', 64)->nullable();
            $table->string('freight_forwarder', 191)->nullable();
            $table->string('bl_awb_no', 64)->nullable();
            $table->string('status', 24)->default('draft');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_shipments');
    }
};
