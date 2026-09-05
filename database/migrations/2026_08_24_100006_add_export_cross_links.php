<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wires the remaining export cross-links now that all tables exist:
 *   - commercial_invoices.export_shipment_id (circular with export_shipments)
 *   - delivery_orders.{proforma_invoice_id, letter_of_credit_id,
 *       commercial_invoice_id, export_shipment_id}
 *
 * All columns are NULLABLE additions — the existing local Delivery Order workflow
 * is untouched. An export DO simply gains optional references to its documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_invoices', function (Blueprint $table) {
            $table->foreignId('export_shipment_id')->nullable()->after('letter_of_credit_id')
                ->constrained('export_shipments')->nullOnDelete();
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->foreignId('proforma_invoice_id')->nullable()->after('sales_order_id')
                ->constrained('proforma_invoices')->nullOnDelete();
            $table->foreignId('letter_of_credit_id')->nullable()->after('proforma_invoice_id')
                ->constrained('letters_of_credit')->nullOnDelete();
            $table->foreignId('commercial_invoice_id')->nullable()->after('letter_of_credit_id')
                ->constrained('commercial_invoices')->nullOnDelete();
            $table->foreignId('export_shipment_id')->nullable()->after('commercial_invoice_id')
                ->constrained('export_shipments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proforma_invoice_id');
            $table->dropConstrainedForeignId('letter_of_credit_id');
            $table->dropConstrainedForeignId('commercial_invoice_id');
            $table->dropConstrainedForeignId('export_shipment_id');
        });

        Schema::table('commercial_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('export_shipment_id');
        });
    }
};
