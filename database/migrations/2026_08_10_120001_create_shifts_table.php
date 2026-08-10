<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shift templates (spec #24 HR): a named working window with a break, reused by
 * attendance and (later) rostering. A shift whose end is earlier than its start
 * is a night shift spanning midnight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
