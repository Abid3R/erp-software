<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enriches the master production plan with the specification detail a textile /
 * garment planner works from: buyer & style/PO references, colour and fabric spec
 * (composition, GSM, width, type), the buyer order quantity (distinct from the
 * production quantity), and the booking / shipment dates. All nullable/additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_plans', function (Blueprint $table) {
            $table->string('buyer')->nullable()->after('customer_id');
            $table->string('style_no')->nullable()->after('buyer');
            $table->string('po_no')->nullable()->after('style_no');          // buyer PO
            $table->string('colour')->nullable()->after('po_no');
            $table->string('fabric_composition')->nullable()->after('colour');
            $table->string('gsm')->nullable()->after('fabric_composition');
            $table->string('fabric_width')->nullable()->after('gsm');
            $table->string('fabric_type')->nullable()->after('fabric_width');
            $table->decimal('order_quantity', 18, 4)->nullable()->after('planned_quantity');
            $table->string('order_unit')->nullable()->after('order_quantity'); // e.g. PCS, DZN
            $table->date('booking_date')->nullable()->after('plan_date');
            $table->date('shipment_date')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('production_plans', function (Blueprint $table) {
            $table->dropColumn([
                'buyer', 'style_no', 'po_no', 'colour', 'fabric_composition', 'gsm',
                'fabric_width', 'fabric_type', 'order_quantity', 'order_unit',
                'booking_date', 'shipment_date',
            ]);
        });
    }
};
