<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dyeing type (one-part / two-part) chosen on the approved lab dip per the approved
 * recipe. Drives the dyeing-phase sub-steps of a production plan. Nullable/additive —
 * lab dips without it keep the existing single-dyeing behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_dips', function (Blueprint $table) {
            $table->string('dyeing_type')->nullable()->after('dyeing_process');
        });
    }

    public function down(): void
    {
        Schema::table('lab_dips', function (Blueprint $table) {
            $table->dropColumn('dyeing_type');
        });
    }
};
