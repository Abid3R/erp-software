<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds real dye-house parameters to the lab dip (colour-development record) so an
 * approved lab dip carries the recipe bulk dyeing must reproduce: dyeing process
 * (reactive/disperse/…), substrate, GSM, liquor ratio, temperature, time, pH,
 * shade %, and the wash/rubbing/light fastness grades. All nullable — a plain
 * colour approval can still be recorded with just a name/reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_dips', function (Blueprint $table) {
            $table->string('dyeing_process')->nullable()->after('colour_ref'); // reactive/disperse/pigment/direct
            $table->string('substrate')->nullable()->after('dyeing_process');  // fabric/yarn type
            $table->string('gsm')->nullable()->after('substrate');
            $table->string('liquor_ratio')->nullable()->after('gsm');          // e.g. "1:8"
            $table->decimal('temperature', 6, 2)->nullable()->after('liquor_ratio'); // deg C
            $table->unsignedInteger('dyeing_time')->nullable()->after('temperature'); // minutes
            $table->decimal('ph', 4, 2)->nullable()->after('dyeing_time');
            $table->decimal('shade_percentage', 7, 3)->nullable()->after('ph');
            $table->string('fastness_wash')->nullable()->after('shade_percentage');
            $table->string('fastness_rubbing')->nullable()->after('fastness_wash');
            $table->string('fastness_light')->nullable()->after('fastness_rubbing');
        });
    }

    public function down(): void
    {
        Schema::table('lab_dips', function (Blueprint $table) {
            $table->dropColumn([
                'dyeing_process', 'substrate', 'gsm', 'liquor_ratio', 'temperature',
                'dyeing_time', 'ph', 'shade_percentage', 'fastness_wash', 'fastness_rubbing',
                'fastness_light',
            ]);
        });
    }
};
