<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds accounting-posting tracking to payroll runs (spec Phase 17). journal_id links
 * the posted salary journal, posted_at records when. Additive only — existing data
 * is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete()->after('status');
            $table->timestamp('posted_at')->nullable()->after('journal_id');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journal_id');
            $table->dropColumn('posted_at');
        });
    }
};
