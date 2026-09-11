<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the shared process order with textile capabilities, all additive/nullable
 * so the existing in-house flow is unaffected:
 *
 *  - Sub-contract (job-work) mode: the run is knitted/dyed by an outside supplier
 *    for a service charge. Captures the sub-contractor, the non-stock service item,
 *    the billed quantity × rate, currency, the posted charge and an idempotency
 *    stamp, plus an optional link to the raised supplier bill.
 *  - Fabric header attributes that print on the job card and drive reporting
 *    (composition, GSM, width/"dia", colour) — promoted to real columns.
 *  - Free-form technical parameters (machine dia, gauge, stitch length, liquor
 *    ratio, …) in a single JSON column rendered as category-specific form fields.
 *  - Links to the sales order and the production plan/stage that scheduled the run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_orders', function (Blueprint $table) {
            // Execution mode.
            $table->string('mode')->default('in_house')->after('process_type_id');

            // Sub-contract (job-work) details.
            $table->foreignId('subcontractor_id')->nullable()->after('operator_id')
                ->constrained('suppliers')->nullOnDelete();
            $table->foreignId('service_item_id')->nullable()->after('subcontractor_id')
                ->constrained('products')->nullOnDelete();
            $table->decimal('service_rate', 15, 4)->nullable()->after('service_item_id');
            $table->decimal('bill_quantity', 18, 4)->nullable()->after('service_rate');
            $table->string('service_currency', 3)->nullable()->after('bill_quantity');
            $table->decimal('service_charge', 15, 2)->default(0)->after('service_currency');
            $table->timestamp('service_charged_at')->nullable()->after('service_charge');
            $table->foreignId('supplier_invoice_id')->nullable()->after('service_charged_at')
                ->constrained('supplier_invoices')->nullOnDelete();

            // Fabric header attributes (printed + reportable).
            $table->string('fabric_composition')->nullable()->after('output_product_id');
            $table->string('gsm')->nullable()->after('fabric_composition');       // may be a range e.g. "280/290"
            $table->string('fabric_width')->nullable()->after('gsm');             // e.g. "72 Inch Open"
            $table->string('colour')->nullable()->after('fabric_width');
            $table->string('colour_ref')->nullable()->after('colour');

            // Free-form technical parameters (dia, gauge, stitch length, liquor ratio, …).
            $table->json('specifications')->nullable()->after('colour_ref');

            // Scheduling / order linkage.
            $table->foreignId('sales_order_id')->nullable()->after('manufacturing_order_id')
                ->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('production_plan_id')->nullable()->after('sales_order_id')
                ->constrained('production_plans')->nullOnDelete();
            $table->foreignId('production_plan_stage_id')->nullable()->after('production_plan_id')
                ->constrained('production_plan_stages')->nullOnDelete();

            $table->index(['company_id', 'mode']);
            $table->index('subcontractor_id');
            $table->index('production_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('process_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subcontractor_id');
            $table->dropConstrainedForeignId('service_item_id');
            $table->dropConstrainedForeignId('supplier_invoice_id');
            $table->dropConstrainedForeignId('sales_order_id');
            $table->dropConstrainedForeignId('production_plan_id');
            $table->dropConstrainedForeignId('production_plan_stage_id');
            $table->dropColumn([
                'mode', 'service_rate', 'bill_quantity', 'service_currency', 'service_charge',
                'service_charged_at', 'fabric_composition', 'gsm', 'fabric_width', 'colour',
                'colour_ref', 'specifications',
            ]);
        });
    }
};
