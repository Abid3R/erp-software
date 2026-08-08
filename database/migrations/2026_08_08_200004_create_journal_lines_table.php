<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal lines (spec #11, #12). Each line is a debit XOR a credit to one account,
 * both non-negative. A DB CHECK enforces the "exactly one side, non-negative"
 * rule; the balanced-total invariant (Sum debit = Sum credit) is enforced by the
 * posting engine and (in hardening) a per-journal DB trigger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->decimal('debit', 19, 2)->default(0);
            $table->decimal('credit', 19, 2)->default(0);
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index('journal_id');
            $table->index('account_id');
        });

        // Non-negative amounts and exactly one populated side (spec #28).
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_nonneg
            CHECK (debit >= 0 AND credit >= 0)');
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_one_side
            CHECK (NOT (debit > 0 AND credit > 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
