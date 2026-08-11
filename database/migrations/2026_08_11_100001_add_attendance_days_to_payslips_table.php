<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the attendance basis of a payslip (spec #24): days worked and unpaid
 * absences in the pay period, so an absence deduction is explainable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->unsignedSmallInteger('worked_days')->default(0)->after('basic');
            $table->unsignedSmallInteger('absent_days')->default(0)->after('worked_days');
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['worked_days', 'absent_days']);
        });
    }
};
