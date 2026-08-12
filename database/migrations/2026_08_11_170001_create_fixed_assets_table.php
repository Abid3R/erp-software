<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed asset register (spec: Accounts → Fixed Assets). Straight-line depreciation
 * over useful_life_months; accumulated_depreciation is the running total posted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('asset_code', 32);
            $table->string('name');
            $table->string('category')->nullable();
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 19, 2);
            $table->decimal('salvage_value', 19, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_months');
            $table->decimal('accumulated_depreciation', 19, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'asset_code']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
