<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Units of measurement. A base unit has base_unit_id = null and factor = 1
 * (e.g. Piece). A derived unit references a base and stores how many base units
 * it represents (e.g. Carton, factor 24). Conversions use exact decimal math —
 * never floats (spec #19, #52).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('base_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->decimal('factor', 19, 6)->default(1); // base units per 1 of this unit
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
