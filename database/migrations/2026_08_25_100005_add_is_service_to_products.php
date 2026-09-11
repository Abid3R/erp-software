<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a product as a non-stock *service* item (e.g. "Service Charge for
 * Knitting"). Service items are billed on sub-contract / job-work but never move
 * as inventory, so they are excluded from stock actions and offered as the service
 * charge item on sub-contract orders. Non-breaking — defaults to false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_service')->default(false)->after('tracks_serial');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_service');
        });
    }
};
