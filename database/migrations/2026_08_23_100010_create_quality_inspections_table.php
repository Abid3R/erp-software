<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quality inspections for production output (knitting, dyeing, finishing, finished
 * products). Polymorphic to the thing inspected (a process order) and linked to
 * the specific batch. Rejected quantity is removed from available stock by the
 * QC action, so a failed inspection never leaves saleable goods on hand.
 * Company-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('reference');
            $table->nullableMorphs('inspectable');                 // e.g. ProcessOrder
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('inspected_quantity', 18, 4)->default(0);
            $table->decimal('passed_quantity', 18, 4)->default(0);
            $table->decimal('rejected_quantity', 18, 4)->default(0);
            $table->text('defects')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status');                              // passed, partial, failed
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'status']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_inspections');
    }
};
