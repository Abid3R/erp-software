<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §16 & §17 — controlled rework and QC stage tagging. A rework run is a normal
 * process order that reprocesses rejected output; it links back to the original
 * order (which is never modified). QC inspections gain a stage tag (knitting,
 * dyeing, finishing, …) for stage-wise QC reporting. All additive/nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_orders', function (Blueprint $table) {
            $table->boolean('is_rework')->default(false)->after('mode');
            $table->foreignId('rework_of_process_order_id')->nullable()->after('is_rework')
                ->constrained('process_orders')->nullOnDelete();
        });

        Schema::table('quality_inspections', function (Blueprint $table) {
            $table->string('stage')->nullable()->after('inspectable_id');
        });
    }

    public function down(): void
    {
        Schema::table('process_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rework_of_process_order_id');
            $table->dropColumn('is_rework');
        });
        Schema::table('quality_inspections', function (Blueprint $table) {
            $table->dropColumn('stage');
        });
    }
};
