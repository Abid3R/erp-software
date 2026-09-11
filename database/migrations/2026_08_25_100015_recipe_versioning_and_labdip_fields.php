<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §11 & §10 — version-controlled recipes and lab-dip enrichment.
 *  - product/dyeing specifications become versioned, approvable recipes
 *    (recipe number, version, approval status/by/at).
 *  - lab dips gain sales-order/PI references, approval by/at and a recipe version.
 * All additive/nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['product_specifications', 'dyeing_specifications'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('recipe_number')->nullable()->after('product_id');
                $table->unsignedInteger('version')->default(1)->after('recipe_number');
                $table->string('approval_status')->default('draft')->after('version'); // draft/approved
                $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            });
        }

        Schema::table('lab_dips', function (Blueprint $table) {
            $table->foreignId('sales_order_id')->nullable()->after('customer_id')->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('proforma_invoice_id')->nullable()->after('sales_order_id')->constrained('proforma_invoices')->nullOnDelete();
            $table->string('recipe_version')->nullable()->after('recipe');
            $table->foreignId('approved_by')->nullable()->after('recipe_version')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        foreach (['product_specifications', 'dyeing_specifications'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropConstrainedForeignId('approved_by');
                $table->dropColumn(['recipe_number', 'version', 'approval_status', 'approved_at']);
            });
        }
        Schema::table('lab_dips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_order_id');
            $table->dropConstrainedForeignId('proforma_invoice_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['recipe_version', 'approved_at']);
        });
    }
};
