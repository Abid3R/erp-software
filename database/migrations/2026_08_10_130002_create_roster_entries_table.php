<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One roster cell: which shift (or day off) an employee has on a given date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roster_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('roster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_off')->default(false);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['roster_id', 'employee_id', 'date']);
            $table->index(['company_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_entries');
    }
};
