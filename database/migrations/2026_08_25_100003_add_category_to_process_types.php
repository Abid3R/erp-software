<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Classifies each process type into a textile category (knitting, dyeing,
 * finishing, printing, other). The category drives which technical spec fields the
 * process-order form shows and enables textile reporting by stage. Existing rows
 * are back-filled from their well-known codes; anything unknown falls back to
 * "finishing" (the safe generic stage). Non-breaking — nullable with a default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_types', function (Blueprint $table) {
            $table->string('category')->default('other')->after('name');
            $table->boolean('subcontractable')->default(false)->after('requires_qc');
        });

        // Back-fill categories from the seeded codes so existing data is meaningful.
        $map = [
            'KNIT' => 'knitting',
            'DYE' => 'dyeing',
            'WASH' => 'finishing',
            'COMP' => 'finishing',
            'STEN' => 'finishing',
            'FINISH' => 'finishing',
            'PRINT' => 'printing',
        ];
        foreach ($map as $code => $category) {
            DB::table('process_types')->where('code', $code)->update(['category' => $category]);
        }
        // Knitting is the process most commonly sub-contracted in Bangladesh.
        DB::table('process_types')->where('code', 'KNIT')->update(['subcontractable' => true]);
    }

    public function down(): void
    {
        Schema::table('process_types', function (Blueprint $table) {
            $table->dropColumn(['category', 'subcontractable']);
        });
    }
};
