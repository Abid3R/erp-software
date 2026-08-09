<?php

namespace Database\Seeders;

use App\Actions\Payments\RecordCustomerReceipt;
use App\Actions\Purchasing\ReceiveGoods;
use App\Actions\Sales\RecordSale;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Journal;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
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

            // Current fiscal year, open.
            $year = (int) date('Y');
            AccountingPeriod::updateOrCreate(
                ['start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31"],
                ['name' => "FY{$year}", 'fiscal_year' => $year, 'status' => PeriodStatus::Open],
            );

            // Default chart of accounts (spec #11).
            $coa = [
                ['1000', 'Cash', AccountType::Asset],
                ['1010', 'Bank', AccountType::Asset],
                ['1100', 'Accounts Receivable', AccountType::Asset],
                ['1200', 'Inventory', AccountType::Asset],
                ['1250', 'Input VAT Receivable', AccountType::Asset],
                ['2000', 'Accounts Payable', AccountType::Liability],
                ['2100', 'Goods Received Not Invoiced', AccountType::Liability],
                ['2200', 'Output VAT Payable', AccountType::Liability],
                ['3000', 'Share Capital', AccountType::Equity],
                ['3100', 'Retained Earnings', AccountType::Equity],
                ['4000', 'Sales Revenue', AccountType::Revenue],
                ['4100', 'Sales Returns', AccountType::Revenue],
                ['5000', 'Cost of Goods Sold', AccountType::Expense],
                ['5100', 'Inventory Adjustment', AccountType::Expense],
                ['5200', 'Operating Expenses', AccountType::Expense],
            ];

            foreach ($coa as [$code, $accountName, $type]) {
                Account::updateOrCreate(
                    ['code' => $code],
                    ['name' => $accountName, 'type' => $type, 'is_postable' => true, 'is_active' => true],
                );
            }

            $customer = Customer::updateOrCreate(['code' => 'CUST-001'], ['name' => 'Acme Retail', 'is_active' => true]);
            Supplier::updateOrCreate(['code' => 'SUP-001'], ['name' => 'Global Supplies Ltd.', 'is_active' => true]);

            // Demo transactions so the dashboard shows real figures. Guarded so a
            // repeat `db:seed` does not double-post to the immutable ledger.
            $warehouse = Warehouse::query()->where('code', 'MAIN')->first();
            $demoProduct = Product::query()->where('sku', 'PAP-A4')->first();

            if ($warehouse !== null && $demoProduct !== null && Journal::query()->doesntExist()) {
                $today = date('Y-m-d');
                app(ReceiveGoods::class)->handle($warehouse, $demoProduct, '100', '320', $today);
                app(RecordSale::class)->handle($warehouse, $demoProduct, '15', '420', $today, customer: $customer);
                app(RecordCustomerReceipt::class)->handle($customer, '3000', PaymentMethod::Cash, $today, 'SEED-RCPT-1');
            }
        });

        $this->command->info("Seeded admin {$adminCfg['email']} + demo company ({$company->code}) + demo catalog.");
    }
}
