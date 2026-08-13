<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('ordered_quantity', 19, 4)->default(0);
            $table->decimal('received_quantity', 19, 4)->default(0);
            $table->decimal('accepted_quantity', 19, 4)->default(0);
            $table->decimal('rejected_quantity', 19, 4)->default(0);
            $table->decimal('unit_cost', 19, 4)->default(0);
            $table->string('batch_no', 64)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('qc_status', 16)->default('pending');
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
    }
};
