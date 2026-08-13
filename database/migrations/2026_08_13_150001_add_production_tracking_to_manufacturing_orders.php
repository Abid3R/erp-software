<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds staged-execution tracking to manufacturing orders (spec: Manufacturing
 * Execution). quantity_produced accumulates across partial production runs;
 * wip_cost holds the value currently sitting in Work-In-Progress (materials issued
 * but not yet turned into finished goods). Additive only — existing data is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacturing_orders', function (Blueprint $table) {
            $table->decimal('quantity_produced', 19, 4)->default(0)->after('quantity');
            $table->decimal('wip_cost', 19, 2)->default(0)->after('total_cost');
            $table->timestamp('materials_issued_at')->nullable()->after('wip_cost');
        });
    }

    public function down(): void
    {
        Schema::table('manufacturing_orders', function (Blueprint $table) {
            $table->dropColumn(['quantity_produced', 'wip_cost', 'materials_issued_at']);
        });
    }
};
