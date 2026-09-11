<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §4 — configurable process routes (routings). A route is a reusable, ordered
 * sequence of process types for producing a product (e.g. Yarn → Knitting → Grey →
 * Dyeing → Finishing). Production plans are generated from the product's route when
 * one exists, so production is not hard-coded to a single sequence. Company-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete(); // finished product this route makes
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'product_id']);
        });

        Schema::create('process_route_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('process_route_id')->constrained('process_routes')->cascadeOnDelete();
            $table->foreignId('process_type_id')->constrained('process_types')->restrictOnDelete();
            $table->foreignId('output_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['process_route_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_route_steps');
        Schema::dropIfExists('process_routes');
    }
};
