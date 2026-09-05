<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packing List (spec: Export). Details how an export consignment is packed —
 * cartons/rolls, net and gross weights, marks & numbers. Generated from a
 * Commercial Invoice / Delivery Order so quantities are not re-keyed. Purely a
 * documentary record; no ledger or stock impact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32);
            $table->date('pl_date');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('commercial_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('export_shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_order_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('total_packages')->nullable();
            $table->string('status', 16)->default('draft');
            $table->string('marks_numbers')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('packing_list_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('packing_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('package_no', 64)->nullable();
            $table->string('carton_no', 64)->nullable();
            $table->decimal('quantity', 19, 4)->default(0);
            $table->decimal('net_weight', 19, 4)->nullable();
            $table->decimal('gross_weight', 19, 4)->nullable();
            $table->string('dimensions', 64)->nullable();
            $table->string('marks_numbers', 128)->nullable();
            $table->timestamps();

            $table->index('packing_list_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_list_lines');
        Schema::dropIfExists('packing_lists');
    }
};
