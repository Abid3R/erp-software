<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable tax/VAT rates (spec: Tax/VAT configuration — Phase 4). Rates are
 * data, never hard-coded: each has an effective window so a rate change is a new
 * row, not a code change. Optional input/output VAT accounts override the config
 * defaults. Bangladesh VAT values are seeded but fully editable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);              // e.g. VAT15, VAT7_5, EXEMPT
            $table->string('name');                  // e.g. "Standard VAT 15%"
            $table->decimal('rate_percent', 8, 4);   // 15.0000
            $table->boolean('is_inclusive')->default(false); // price includes tax?
            $table->foreignId('input_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('output_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
