<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ordered stages of a production plan (Knitting → Dyeing → Finishing → …).
 * Each stage is the *intent* for one step: planned quantity, dates and assigned
 * machine. The actual execution is a process order spawned from the stage; the
 * stage's progress is a live roll-up of its linked process orders. Company-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_plan_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_plan_id')->constrained('production_plans')->cascadeOnDelete();
            $table->foreignId('process_type_id')->constrained('process_types')->restrictOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->foreignId('output_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->decimal('planned_quantity', 18, 4)->default(0);
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['production_plan_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_plan_stages');
    }
};
