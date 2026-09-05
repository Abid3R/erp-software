<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Letter of Credit (spec: Export). A bank instrument opened by the buyer's
 * (applicant's) bank in favour of the exporter (beneficiary). Purely a
 * commercial/financial control document — it never posts to the ledger itself.
 * One LC covers many Proforma Invoices; utilisation is computed from the linked
 * PIs, so no allocated/remaining amount is stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letters_of_credit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->date('lc_date');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('beneficiary', 191)->nullable();
            $table->string('issuing_bank', 191)->nullable();
            $table->string('advising_bank', 191)->nullable();
            $table->decimal('amount', 19, 4)->default(0);
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 19, 6)->default(1);
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('latest_shipment_date')->nullable();
            $table->string('payment_terms', 191)->nullable();
            $table->string('port_of_loading', 128)->nullable();
            $table->string('port_of_discharge', 128)->nullable();
            $table->string('description')->nullable();
            $table->string('status', 24)->default('draft');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letters_of_credit');
    }
};
