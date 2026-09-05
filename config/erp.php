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
        // Server-rendered report PDFs use DomPDF's bundled DejaVu Sans font, which
        // has no Bengali Taka glyph (U+09F3). So those reports print an ASCII-safe
        // label instead — "Tk"/"BDT" is the convention on Bangladeshi system-
        // generated reports. Browser-printed documents keep the real ৳ symbol.
        'pdf_symbol' => env('PDF_CURRENCY_SYMBOL', 'Tk '),
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
        'salary_expense' => '5400',
        'employee_payable' => '2300',
        'opening_balance_equity' => '3900',
        // Conversion costs (labour, machine, utilities, overhead) capitalised into
        // WIP during production costing are credited here until settled.
        'accrued_overhead' => '2400',
    ],

    // Sales & Export workflow. Values are configurable, never hard-coded in the
    // documents — a company can trim or extend these lists via the environment.
    'export' => [
        // Incoterms offered on PI / Commercial Invoice / Shipment.
        'incoterms' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'EXPORT_INCOTERMS',
            'FOB,CFR,CIF,EXW,FCA,CPT,CIP,DAP,DPU,DDP',
        ))))),
        'default_incoterm' => env('EXPORT_DEFAULT_INCOTERM', 'FOB'),
        // Payment-term presets offered on PI / LC / Commercial Invoice.
        'payment_terms' => array_values(array_filter(array_map('trim', explode('|', (string) env(
            'EXPORT_PAYMENT_TERMS',
            'At sight L/C|30 days L/C|60 days L/C|90 days L/C|Advance TT|30 days TT',
        ))))),
        // Currencies offered on export documents (base currency is always allowed).
        'currencies' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'EXPORT_CURRENCIES',
            'USD,EUR,GBP,BDT',
        ))))),
        'default_currency' => env('EXPORT_DEFAULT_CURRENCY', 'USD'),
    ],

];
