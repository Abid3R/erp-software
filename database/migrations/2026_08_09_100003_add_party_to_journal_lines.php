<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional party (customer/supplier) on a journal line. Lets control-account
 * lines (Accounts Receivable / Payable) carry the party, so per-party balances
 * are a subledger derived from the one authoritative ledger (spec #65) — no
 * separately-maintained AR/AP balances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->nullableMorphs('party');
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropMorphs('party');
        });
    }
};
