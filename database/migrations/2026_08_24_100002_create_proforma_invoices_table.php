<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proforma Invoice (spec: Export). A quotation-grade commercial document sent to
 * the buyer before shipment, usually to open an LC. It pulls its lines from a
 * Sales Order and can be allocated against one LC. Informational only — it never
 * touches inventory or the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32);
            $table->date('pi_date');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('letter_of_credit_id')->nullable()->constrained('letters_of_credit')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 19, 6)->default(1);
            $table->string('payment_terms', 191)->nullable();
            $table->string('incoterm', 32)->nullable();
            $table->string('delivery_terms', 191)->nullable();
            $table->decimal('discount', 19, 4)->default(0);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->string('status', 16)->default('draft');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index('letter_of_credit_id');
        });

        Schema::create('proforma_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proforma_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_price', 19, 4);
            $table->timestamps();

            $table->index('proforma_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_invoice_lines');
        Schema::dropIfExists('proforma_invoices');
    }
};
