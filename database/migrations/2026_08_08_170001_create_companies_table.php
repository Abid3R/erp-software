<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Companies — the top of the organization hierarchy and the tenancy boundary.
 * Per-company configuration (currency, fiscal year, tax, stock policy) lives here
 * so business behaviour is configured, not hard-coded (spec #6, #40, #54).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('code')->unique();               // short identifier, e.g. ACME
            $table->string('currency_code', 3)->default('BDT');
            $table->string('currency_symbol', 8)->default('৳');
            $table->string('timezone')->default('UTC');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1); // 1=Jan..12
            $table->string('tax_registration_number')->nullable();             // VAT/BIN
            $table->decimal('default_tax_rate', 6, 3)->nullable();             // e.g. 15.000 (%)
            $table->boolean('allow_negative_stock')->default(false);          // company default policy
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
