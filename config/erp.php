<?php

/*
|--------------------------------------------------------------------------
| ERP core configuration
|--------------------------------------------------------------------------
| Central, environment-driven settings so business behaviour is configured,
| not hard-coded (spec #51, #54). Per-company overrides live in the database
| (company settings) as modules are built; these are the system defaults.
*/

return [

    'currency' => [
        'code' => env('DEFAULT_CURRENCY', 'BDT'),
        'symbol' => env('DEFAULT_CURRENCY_SYMBOL', '৳'),
        // Decimal places for monetary values (spec #52).
        'precision' => (int) env('CURRENCY_PRECISION', 2),
    ],

    // Precision for stock quantities and unit costs (weighted average — spec #15).
    'quantity_precision' => (int) env('QUANTITY_PRECISION', 4),
    'cost_precision' => (int) env('COST_PRECISION', 6),

    // Initial administrator, provisioned by DatabaseSeeder. Never a hard-coded
    // production password — supply real values via the environment (spec #59).
    'admin' => [
        'name' => env('ADMIN_NAME', 'System Administrator'),
        'email' => env('ADMIN_EMAIL', 'admin@erp.test'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

    // Payroll: nominal paid working days per month, used to derive the per-day
    // rate for absence deductions (26 is the common BD convention).
    'payroll' => [
        'working_days' => (int) env('PAYROLL_WORKING_DAYS', 26),
    ],

    // HR: weekly off days as ISO weekday numbers (Mon=1 .. Sun=7). Bangladesh
    // commonly rests Friday (5); set HR_WEEKEND_DAYS=5,6 for a Fri+Sat weekend.
    'hr' => [
        'weekend_days' => array_values(array_filter(array_map(
            fn (string $d): int => (int) trim($d),
            explode(',', (string) env('HR_WEEKEND_DAYS', '5')),
        ))),
    ],

    // Maps posting roles to chart-of-accounts codes, so standard journal entries
    // reference accounts by role, not hard-coded ids (spec #51, #54). These codes
    // match the default COA seeded in DatabaseSeeder; a company may remap them.
    'accounts' => [
        'cash' => '1000',
        'bank' => '1010',
        'receivable' => '1100',
        'inventory' => '1200',
        'wip' => '1300',
        'input_vat' => '1250',
        'payable' => '2000',
        'grni' => '2100',
        'output_vat' => '2200',
        'sales' => '4000',
        'sales_returns' => '4100',
        'cogs' => '5000',
        'inventory_adjustment' => '5100',
        'fixed_assets' => '1500',
        'accumulated_depreciation' => '1600',
        'depreciation_expense' => '5300',
        'opening_balance_equity' => '3900',
    ],

];
