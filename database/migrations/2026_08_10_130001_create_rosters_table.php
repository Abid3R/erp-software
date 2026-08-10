<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A generated shift roster for a date range (spec #24 HR). Holds the parameters
 * the rule-based generator used; entries are stored per employee/day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 16)->default('draft');
            $table->unsignedTinyInteger('off_days_per_week')->default(1);
            $table->unsignedSmallInteger('max_hours_per_week')->default(48);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rosters');
    }
};
