<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable textile process types (Knitting, Dyeing, Finishing, and any added
 * later). Behaviour flags let new processes be added without code changes — the
 * shared process engine reads these to decide whether to consume/produce stock,
 * require a lab dip, or require QC. Company-scoped and seeded with sensible
 * defaults for every existing company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('consumes_material')->default(true);  // issues inputs from stock
            $table->boolean('produces_material')->default(true);  // receives output into stock
            $table->boolean('requires_lab_dip')->default(false);  // e.g. dyeing
            $table->boolean('requires_qc')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        // Seed the three standard textile processes for every existing company.
        $defaults = [
            ['KNIT', 'Knitting', true, true, false, true, 1],
            ['DYE', 'Dyeing', true, true, true, true, 2],
            ['FINISH', 'Finishing', true, true, false, true, 3],
        ];

        Company::query()->pluck('id')->each(function ($companyId) use ($defaults): void {
            foreach ($defaults as [$code, $name, $consumes, $produces, $labDip, $qc, $sort]) {
                DB::table('process_types')->updateOrInsert(
                    ['company_id' => $companyId, 'code' => $code],
                    [
                        'name' => $name, 'consumes_material' => $consumes, 'produces_material' => $produces,
                        'requires_lab_dip' => $labDip, 'requires_qc' => $qc, 'sort' => $sort,
                        'is_active' => true, 'updated_at' => now(), 'created_at' => now(),
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_types');
    }
};
