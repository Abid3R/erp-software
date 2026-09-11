<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recipe consumption lines — the per-output-unit material recipe that lets the
 * system calculate how much of each material an order needs. Attached
 * polymorphically to a recipe holder: a fabric specification (knitting → yarn) or
 * a lab dip (dyeing → dyes & chemicals). Each line carries a dosing basis matching
 * Bangladesh practice:
 *   - per_unit      : material units per 1 unit of output (e.g. kg yarn per kg fabric)
 *   - percent_owf   : % on weight of fabric (dyes)
 *   - g_per_litre   : grams per litre of dye bath (salt, soda, auxiliaries)
 * plus a process-loss / wastage % added on top. Company-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('holder_type');
            $table->unsignedBigInteger('holder_id');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('basis')->default('per_unit');
            $table->decimal('rate', 18, 6)->default(0);           // meaning depends on basis
            $table->decimal('wastage_percent', 7, 3)->default(0); // process loss added on top
            $table->unsignedInteger('sort')->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['holder_type', 'holder_id']);
            $table->index(['company_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_consumptions');
    }
};
