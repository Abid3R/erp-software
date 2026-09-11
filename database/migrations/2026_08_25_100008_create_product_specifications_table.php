<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product fabric/knitting specification master. Define a product's standard
 * spec once (composition, GSM, width, machine dia, gauge, stitch length, …) and it
 * auto-fills the knitting process order / sub-contract when that product is chosen,
 * and prints as an operator spec sheet. One spec per product. Company-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // Fabric header.
            $table->string('fabric_composition')->nullable();
            $table->string('gsm')->nullable();            // may be a range, e.g. "280/290"
            $table->string('fabric_width')->nullable();   // e.g. "72 Inch Open"
            $table->string('colour')->nullable();
            $table->string('colour_ref')->nullable();

            // Knitting parameters.
            $table->string('yarn_count')->nullable();     // e.g. "30s"
            $table->string('fabric_type')->nullable();    // e.g. "Single Jersey"
            $table->string('quality')->nullable();        // e.g. "Combed"
            $table->string('machine_diameter')->nullable();
            $table->string('gauge')->nullable();
            $table->string('stitch_length')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specifications');
    }
};
