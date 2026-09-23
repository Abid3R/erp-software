<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the dyeing-phase sub-step process types used by the one-part / two-part
 * dyeing workflow, for every company, so they run through the shared process engine
 * (issue → WIP → output → QC) like any other process. Idempotent. DYE (main dyeing)
 * already exists and is reused for one-part dyeing.
 */
return new class extends Migration
{
    public function up(): void
    {
        // [code, name, category, requires_qc, sort]
        $steps = [
            ['PRETREAT', 'Pretreatment', 'dyeing', false, 20],
            ['DYE1', 'Part 1 Dyeing', 'dyeing', true, 21],
            ['IWASH', 'Intermediate Wash', 'dyeing', false, 22],
            ['DYE2', 'Part 2 Dyeing', 'dyeing', true, 23],
            ['WASHOFF', 'Wash-off', 'finishing', false, 24],
        ];

        Company::query()->pluck('id')->each(function ($companyId) use ($steps): void {
            foreach ($steps as [$code, $name, $category, $qc, $sort]) {
                DB::table('process_types')->updateOrInsert(
                    ['company_id' => $companyId, 'code' => $code],
                    [
                        'name' => $name, 'category' => $category, 'consumes_material' => true,
                        'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => $qc,
                        'subcontractable' => false, 'sort' => $sort, 'is_active' => true,
                        'updated_at' => now(), 'created_at' => now(),
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        DB::table('process_types')->whereIn('code', ['PRETREAT', 'DYE1', 'IWASH', 'DYE2', 'WASHOFF'])->delete();
    }
};
