<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Admin credentials come from configuration (spec #59 — never hard-code a
     * production password). A demo company is provisioned so the admin has a
     * company membership (required for panel access) and later modules have an
     * organization to hang data on.
     */
    public function run(): void
    {
        /** @var array{name: string, email: string, password: string} $adminCfg */
        $adminCfg = config('erp.admin');

        $admin = User::query()->updateOrCreate(
            ['email' => $adminCfg['email']],
            [
                'name' => $adminCfg['name'],
                'password' => Hash::make($adminCfg['password']),
                'email_verified_at' => now(),
            ],
        );

        /** @var array{code: string, symbol: string} $currency */
        $currency = ['code' => config('erp.currency.code'), 'symbol' => config('erp.currency.symbol')];

        $company = Company::query()->updateOrCreate(
            ['code' => 'DEMO'],
            [
                'name' => 'Demo Company Ltd.',
                'legal_name' => 'Demo Company Limited',
                'currency_code' => $currency['code'],
                'currency_symbol' => $currency['symbol'],
                'timezone' => config('app.timezone', 'UTC'),
                'fiscal_year_start_month' => 1,
                'default_tax_rate' => 15.000,
                'allow_negative_stock' => false,
                'is_active' => true,
            ],
        );

        // Attach admin as the default member of the demo company.
        $admin->companies()->syncWithoutDetaching([
            $company->getKey() => ['is_default' => true],
        ]);

        // A branch and warehouse for the demo company.
        $branch = $company->branches()->updateOrCreate(
            ['code' => 'HO'],
            ['name' => 'Head Office', 'is_active' => true],
        );

        $company->warehouses()->updateOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Warehouse', 'branch_id' => $branch->getKey(), 'is_active' => true],
        );

        // Demo catalog — created within the company context so company_id is
        // auto-stamped and scoped lookups resolve correctly.
        app(CompanyContext::class)->runFor($company, function () {
            $piece = Unit::updateOrCreate(['code' => 'PCS'], ['name' => 'Piece', 'factor' => 1]);
            Unit::updateOrCreate(['code' => 'CTN'], [
                'name' => 'Carton', 'base_unit_id' => $piece->getKey(), 'factor' => 24,
            ]);

            $category = Category::updateOrCreate(['name' => 'General'], ['is_active' => true]);
            $brand = Brand::updateOrCreate(['name' => 'Generic'], ['is_active' => true]);

            $items = [
                ['A4 Paper Ream', 'PAP-A4', 320.00, 420.00],
                ['Ballpoint Pen', 'PEN-BP', 8.00, 15.00],
                ['Stapler', 'STP-01', 180.00, 260.00],
            ];

            foreach ($items as [$name, $sku, $cost, $price]) {
                Product::updateOrCreate(['sku' => $sku], [
                    'name' => $name,
                    'unit_id' => $piece->getKey(),
                    'category_id' => $category->getKey(),
                    'brand_id' => $brand->getKey(),
                    'cost_price' => $cost,
                    'selling_price' => $price,
                    'is_active' => true,
                ]);
            }
        });

        $this->command->info("Seeded admin {$adminCfg['email']} + demo company ({$company->code}) + demo catalog.");
    }
}
