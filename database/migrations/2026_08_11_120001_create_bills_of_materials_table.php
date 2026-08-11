<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bill of Materials (spec: Manufacturing): the recipe to produce output_quantity
 * of a finished product from its component products. Company-scoped; a product may
 * have multiple named BOM versions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_of_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete(); // finished good
            $table->string('name')->default('Default');
            $table->decimal('output_quantity', 19, 4)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'product_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_of_materials');
    }
};
