<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company, per-document-type counters for generating human document numbers
 * (SO-, PO-, INV- …) atomically. Replaces the racy COUNT(*)+1 scheme so two
 * concurrent creates can never mint the same number. See {@see \App\Support\DocumentNumber}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('key');       // document type, e.g. sales_order, purchase_order
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
