<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * Seed configurable finishing operations (Washing, Compacting, Stentering) as
 * process types for every existing company, so finishing is not hard-coded to a
 * single method — users can run any of these (or add their own) through the shared
 * process engine. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $finishing = [
            ['WASH', 'Washing', 4],
            ['COMP', 'Compacting', 5],
            ['STEN', 'Stentering', 6],
        ];

        Company::query()->pluck('id')->each(function ($companyId) use ($finishing): void {
            foreach ($finishing as [$code, $name, $sort]) {
                DB::table('process_types')->updateOrInsert(
                    ['company_id' => $companyId, 'code' => $code],
                    [
                        'name' => $name, 'consumes_material' => true, 'produces_material' => true,
                        'requires_lab_dip' => false, 'requires_qc' => true, 'sort' => $sort,
                        'is_active' => true, 'updated_at' => now(), 'created_at' => now(),
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        DB::table('process_types')->whereIn('code', ['WASH', 'COMP', 'STEN'])->delete();
    }
};
