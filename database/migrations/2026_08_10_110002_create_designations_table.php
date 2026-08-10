<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job titles / designations (spec #24 HR). Company-scoped; optional grade level
 * for ordering seniority.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('level')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designations');
    }
};
