<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily attendance (spec #24 HR). One row per employee per day. Worked/late/
 * overtime minutes are derived from the check times and the shift by
 * AttendanceCalculator and stored for reporting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('status', 16)->default('present');
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'employee_id', 'date']);
            $table->index(['company_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
