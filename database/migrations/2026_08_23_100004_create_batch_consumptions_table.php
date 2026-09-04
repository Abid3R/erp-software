<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parent → child batch links: records which input batches were consumed to
 * produce an output batch, with the quantity used. This is the backbone of
 * batch traceability (walk backwards from a finished batch to the yarn it came
 * from, or forwards to where a yarn batch ended up).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('output_batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('input_batch_id')->constrained('batches')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->timestamps();

            $table->index('output_batch_id');
            $table->index('input_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_consumptions');
    }
};
