<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordered steps of a flow (spec #25): each step requires approval from a holder of
 * a given role. Sequence drives sequential approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_flow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_flow_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('role');                 // Spatie role name required to approve
            $table->string('name')->nullable();     // label, e.g. "Manager approval"
            $table->timestamps();

            $table->unique(['approval_flow_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_flow_steps');
    }
};
