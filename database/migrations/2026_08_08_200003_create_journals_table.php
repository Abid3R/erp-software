<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal headers (spec #11). A posted journal is immutable (spec #10) — enforced
 * at the app layer now and by a DB trigger in the security-hardening phase. Each
 * journal optionally links to the business document that produced it (source_*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_period_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('reference')->nullable();          // document number
            $table->string('memo')->nullable();
            $table->string('status', 16)->default('draft');   // JournalStatus
            $table->nullableMorphs('source');                 // originating document
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
