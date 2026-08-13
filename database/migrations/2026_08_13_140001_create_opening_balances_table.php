<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opening Balances onboarding document (spec: Phase 26/27). Captures a company's
 * starting GL/AR/AP/stock positions at a cutover date when migrating from another
 * system. Posting routes every line through Opening Balance Equity via the existing
 * accounting + inventory engines, so the trial balance stays balanced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32);
            $table->date('as_of_date');
            $table->string('status', 16)->default('draft');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balances');
    }
};
