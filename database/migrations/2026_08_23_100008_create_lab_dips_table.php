<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab dips — colour-development requests. Purely a workflow/record entity: no
 * stock or accounting effect. An approved lab dip becomes selectable on a dyeing
 * process order. Company-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_dips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('reference');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('colour');                       // colour name, e.g. "Navy Blue"
            $table->string('colour_ref')->nullable();       // Pantone / customer reference
            $table->text('recipe')->nullable();             // dye/chemical recipe or specification
            $table->string('sample_ref')->nullable();       // physical sample identifier
            $table->date('request_date')->nullable();
            $table->string('status')->default('draft');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_dips');
    }
};
