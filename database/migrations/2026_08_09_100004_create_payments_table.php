<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments — customer receipts and supplier payments (spec #23). Each links to
 * the journal it posted. A unique idempotency key per company prevents duplicate
 * processing at the database level (spec #23, #24).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 16);                 // PaymentDirection
            $table->nullableMorphs('party');                 // customer or supplier
            $table->date('date');
            $table->decimal('amount', 19, 2);
            $table->string('method', 16);                    // PaymentMethod
            $table->string('reference')->nullable();         // document number
            $table->string('idempotency_key');
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
