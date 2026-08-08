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

];
