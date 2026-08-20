<?php

use App\Models\Company;
use App\Models\Unit;
use App\Support\CompanyContext;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed the commonly-used Bangladesh trading/manufacturing units for every
 * existing company. Idempotent: units are keyed by (company_id, code) and
 * inserted with updateOrCreate — existing records (e.g. PCS, CTN, MTR) are
 * left untouched, and no product quantities or stock balances are changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $baseUnits = [
            ['PCS', 'Piece'],
            ['KG', 'Kilogram'],
            ['GM', 'Gram'],
            ['TON', 'Ton'],
            ['M', 'Meter'],
            ['CM', 'Centimeter'],
            ['MM', 'Millimeter'],
            ['L', 'Liter'],
            ['ML', 'Milliliter'],
            ['YD', 'Yard'],
            ['SQM', 'Square Meter'],
            ['CBM', 'Cubic Meter'],
            ['BOX', 'Box'],
            ['PACK', 'Pack'],
            ['ROLL', 'Roll'],
            ['SET', 'Set'],
            ['PAIR', 'Pair'],
            ['BDL', 'Bundle'],
            ['BAG', 'Bag'],
            ['BTL', 'Bottle'],
            ['DRM', 'Drum'],
            ['BALE', 'Bale'],
        ];

        $derivedUnits = [
            ['CTN', 'Carton', 'PCS', 24],
            ['DOZ', 'Dozen', 'PCS', 12],
        ];

        $context = app(CompanyContext::class);

        Company::query()->each(function (Company $company) use ($context, $baseUnits, $derivedUnits): void {
            $context->runFor($company, function () use ($baseUnits, $derivedUnits): void {
                foreach ($baseUnits as [$code, $name]) {
                    Unit::withoutEvents(function () use ($code, $name): void {
                        // updateOrCreate keeps existing rows intact (name/factor
                        // on a matched row are only refreshed with the same
                        // values); base_unit_id defaults to null (base unit).
                        Unit::query()->updateOrCreate(
                            ['company_id' => app(CompanyContext::class)->currentId(), 'code' => $code],
                            ['name' => $name, 'factor' => 1, 'is_active' => true],
                        );
                    });
                }

                foreach ($derivedUnits as [$code, $name, $baseCode, $factor]) {
                    $base = Unit::query()
                        ->where('company_id', app(CompanyContext::class)->currentId())
                        ->where('code', $baseCode)
                        ->first();

                    if ($base === null) {
                        continue;
                    }

                    // Only create if missing — never overwrite a derived unit's
                    // existing factor (a company may have customised it).
                    Unit::query()->firstOrCreate(
                        ['company_id' => app(CompanyContext::class)->currentId(), 'code' => $code],
                        ['name' => $name, 'base_unit_id' => $base->getKey(), 'factor' => $factor, 'is_active' => true],
                    );
                }
            });
        });
    }

    public function down(): void
    {
        // Non-destructive: seeded units remain on rollback. Users may have
        // attached products to them; removing would risk data integrity.
    }
};
