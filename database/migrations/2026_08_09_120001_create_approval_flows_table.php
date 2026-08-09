<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable approval flows (spec #25, #51): which approvals apply to a subject
 * type within an amount band. Rules are data, never hard-coded thresholds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('subject_type');                       // approvable model class
            $table->decimal('min_amount', 19, 2)->default(0);
            $table->decimal('max_amount', 19, 2)->nullable();     // null = no upper bound
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'subject_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_flows');
    }
};
