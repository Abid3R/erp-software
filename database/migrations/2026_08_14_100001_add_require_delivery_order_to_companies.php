<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company policy: when true, sales orders must be delivered via a Delivery Order
 * (challan) document; when false the existing direct FulfillSalesOrder flow is used.
 * Defaults to false so existing companies keep working as-is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('require_delivery_order')->default(false)->after('allow_negative_stock');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('require_delivery_order');
        });
    }
};
