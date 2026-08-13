<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balance_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opening_balance_id')->constrained()->cascadeOnDelete();
            $table->string('type', 8); // gl | ar | ap | stock

            // GL
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('side', 8)->nullable(); // debit | credit (GL only)

            // AR / AP
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            // Stock
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 19, 4)->nullable();
            $table->decimal('unit_cost', 19, 6)->nullable();

            // GL / AR / AP amount
            $table->decimal('amount', 19, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_lines');
    }
};
