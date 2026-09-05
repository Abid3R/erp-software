<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial Invoice (spec: Export). The legal invoice raised against the buyer
 * for an export shipment. Unlike the informational PI/LC, this one posts AR:
 * Dr Receivable / Cr Sales (+ Cr Output VAT), converted from its foreign currency
 * to the base currency at exchange_rate. COGS is NOT posted here — the linked
 * Delivery Order already booked it when the goods left the warehouse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32);
            $table->date('invoice_date');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('proforma_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('letter_of_credit_id')->nullable()->constrained('letters_of_credit')->nullOnDelete();
            // export_shipment_id is added later (circular ref) in the cross-links migration.
            $table->string('consignee', 191)->nullable();
            $table->string('buyer', 191)->nullable();
            $table->string('country_of_origin', 96)->nullable();
            $table->string('destination_country', 96)->nullable();
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 19, 6)->default(1);
            $table->string('incoterm', 32)->nullable();
            $table->string('payment_terms', 191)->nullable();
            $table->decimal('discount', 19, 4)->default(0);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->string('status', 16)->default('draft');
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->string('terms')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('commercial_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commercial_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('hs_code', 32)->nullable();
            $table->string('description')->nullable();
            $table->decimal('quantity', 19, 4);
            $table->string('unit', 16)->nullable();
            $table->decimal('unit_price', 19, 4);
            $table->timestamps();

            $table->index('commercial_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_invoice_lines');
        Schema::dropIfExists('commercial_invoices');
    }
};
