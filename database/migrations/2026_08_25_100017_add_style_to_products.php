<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional garment/order style (e.g. MS09B) to the product — the "Item" a
 * fabric belongs to on a buyer booking. Lets an order group its fabrics by item
 * (Body / Rib / … under each style). Nullable/additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('style')->nullable()->after('colour');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('style');
        });
    }
};
