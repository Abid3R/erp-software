<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed knitting-machine attributes (cylinder diameter in inches, gauge in
 * needles/inch, feeder & needle counts). These describe the machine itself; the
 * per-run values (stitch length, actual dia used on a sub-contract) live on the
 * process order. All nullable — non-textile machines simply leave them blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->decimal('diameter', 6, 2)->nullable()->after('type');   // cylinder dia (inches)
            $table->unsignedSmallInteger('gauge')->nullable()->after('diameter'); // needles per inch
            $table->unsignedSmallInteger('feeder_count')->nullable()->after('gauge');
            $table->unsignedInteger('needle_count')->nullable()->after('feeder_count');
        });
    }

    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->dropColumn(['diameter', 'gauge', 'feeder_count', 'needle_count']);
        });
    }
};
