<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Machines / work centres used by textile process orders (knitting, dyeing,
 * finishing). The hourly cost feeds machine-cost absorption in production
 * costing (added in a later phase). Company-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('type')->nullable();          // e.g. knitting, dyeing, finishing, general
            $table->decimal('hourly_cost', 15, 2)->default(0);   // for machine-cost absorption
            $table->decimal('capacity_per_hour', 18, 4)->nullable(); // optional throughput
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
