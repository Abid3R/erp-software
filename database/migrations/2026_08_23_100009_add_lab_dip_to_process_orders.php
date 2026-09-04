<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a process order (typically dyeing) to the approved lab dip whose colour it
 * is realising. Nullable — only dyeing-type processes require it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_orders', function (Blueprint $table) {
            $table->foreignId('lab_dip_id')->nullable()->after('output_batch_id')
                ->constrained('lab_dips')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('process_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lab_dip_id');
        });
    }
};
