<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM opportunity (spec: CRM): a deal moving through pipeline stages, with an
 * expected value and win probability. Weighted value = value × probability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stage', 16)->default('prospecting');
            $table->decimal('expected_value', 19, 2)->default(0);
            $table->unsignedTinyInteger('probability')->default(0); // 0..100
            $table->date('expected_close_date')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
