<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product dyeing specification master — the standard dyeing program for a
 * dyed-fabric product (process, liquor ratio, temperature, shade, fastness). Its
 * dyeing recipe (dyes % owf, chemicals g/L) lives in recipe_consumptions. It
 * auto-fills a dyeing process order and prints as an operator recipe sheet — the
 * dyeing parallel of the fabric (knitting) specification. One spec per product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dyeing_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->string('dyeing_process')->nullable();  // reactive/disperse/pigment/direct/vat/acid
            $table->string('substrate')->nullable();
            $table->string('gsm')->nullable();
            $table->string('colour')->nullable();
            $table->string('colour_ref')->nullable();
            $table->string('liquor_ratio')->nullable();     // e.g. "1:8"
            $table->decimal('temperature', 6, 2)->nullable();
            $table->unsignedInteger('dyeing_time')->nullable();
            $table->decimal('ph', 4, 2)->nullable();
            $table->decimal('shade_percentage', 7, 3)->nullable();
            $table->string('fastness_wash')->nullable();
            $table->string('fastness_rubbing')->nullable();
            $table->string('fastness_light')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dyeing_specifications');
    }
};
