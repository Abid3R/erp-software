<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §3 & §14 — textile attributes on the single Product master (optional, so normal
 * products are unaffected) and a configurable wastage hierarchy. All additive/
 * nullable: nothing existing changes.
 *
 *  - products: textile flag/type + fabric/yarn/GSM/width/colour/shade/construction,
 *    roll-tracked flag, and a product-level default wastage %.
 *  - process_types: process-level default wastage %.
 *  - process_orders: the expected wastage %/output captured per run and the actual
 *    wastage reason, so expected-vs-actual can be compared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_textile')->default(false)->after('is_service');
            $table->string('textile_type')->nullable()->after('is_textile'); // yarn/grey_fabric/dyed_fabric/finished_fabric/accessory/other
            $table->string('fabric_type')->nullable()->after('textile_type');
            $table->string('yarn_type')->nullable()->after('fabric_type');
            $table->string('gsm')->nullable()->after('yarn_type');
            $table->string('width')->nullable()->after('gsm');
            $table->string('colour')->nullable()->after('width');
            $table->string('shade')->nullable()->after('colour');
            $table->string('construction')->nullable()->after('shade');
            $table->boolean('is_roll_tracked')->default(false)->after('construction');
            $table->decimal('default_wastage_percent', 7, 3)->nullable()->after('is_roll_tracked');
        });

        Schema::table('process_types', function (Blueprint $table) {
            $table->decimal('default_wastage_percent', 7, 3)->nullable()->after('subcontractable');
        });

        Schema::table('process_orders', function (Blueprint $table) {
            $table->decimal('expected_wastage_percent', 7, 3)->nullable()->after('wastage_quantity');
            $table->decimal('expected_output', 18, 4)->nullable()->after('expected_wastage_percent');
            $table->string('wastage_reason')->nullable()->after('expected_output');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'is_textile', 'textile_type', 'fabric_type', 'yarn_type', 'gsm', 'width',
                'colour', 'shade', 'construction', 'is_roll_tracked', 'default_wastage_percent',
            ]);
        });
        Schema::table('process_types', function (Blueprint $table) {
            $table->dropColumn('default_wastage_percent');
        });
        Schema::table('process_orders', function (Blueprint $table) {
            $table->dropColumn(['expected_wastage_percent', 'expected_output', 'wastage_reason']);
        });
    }
};
