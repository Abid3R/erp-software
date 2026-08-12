<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expense voucher (spec: Accounts → Expenses). Records business expenses paid from
 * Cash/Bank or taken on credit. Posting books Dr Expense account(s) / Cr Cash|Bank|
 * Payable through the accounting engine; posted vouchers are immutable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32);
            $table->date('expense_date');
            $table->string('payment_method', 16); // cash | bank | credit
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete(); // for credit
            $table->string('reference')->nullable();
            $table->string('status', 16)->default('draft');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
