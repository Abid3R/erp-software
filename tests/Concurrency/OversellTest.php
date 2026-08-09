<?php

use App\Actions\Inventory\ReceiveStock;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Symfony\Component\Process\Process;

/**
 * True concurrency (spec #17, #47): with 5 units on hand and negative stock
 * disabled, ten real OS processes each try to issue 1 unit at once. The FOR UPDATE
 * lock in IssueStock must serialize them so exactly 5 succeed and stock never goes
 * negative. Runs in the Concurrency suite (not transaction-wrapped) so the data is
 * committed and visible to the child processes; each run uses a fresh company, and
 * the test DB is rebuilt by migrate:fresh on the next suite run.
 */
it('never oversells the last units under concurrent parallel issues', function () {
    $context = app(CompanyContext::class);
    $company = Company::factory()->create(['allow_negative_stock' => false]);
    $context->set($company);

    $warehouse = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Conc', 'code' => 'WH-CONC']);
    $unit = Unit::create(['company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS-C', 'factor' => 1]);
    $product = Product::create([
        'company_id' => $company->getKey(), 'unit_id' => $unit->getKey(),
        'sku' => 'CONC-1', 'name' => 'Contended Widget', 'cost_price' => 0, 'selling_price' => 0,
    ]);
    app(ReceiveStock::class)->handle($warehouse, $product, '5', '10'); // 5 units on hand
    $context->forget();

    /** @var array<string, mixed> $owner */
    $owner = config('database.connections.pgsql_owner');
    $env = [
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => $owner['database'],   // erp_test
        'DB_USERNAME' => $owner['username'],   // erp_owner (can write)
        'DB_PASSWORD' => (string) $owner['password'],
        'DB_HOST' => $owner['host'],
        'DB_PORT' => (string) $owner['port'],
    ];

    // Launch all processes before waiting on any, so they contend.
    $processes = [];
    for ($i = 0; $i < 10; $i++) {
        $process = new Process(
            [PHP_BINARY, 'artisan', 'erp:issue-test', (string) $warehouse->getKey(), (string) $product->getKey(), '1'],
            base_path(),
            $env,
        );
        $process->setTimeout(60);
        $process->start();
        $processes[] = $process;
    }

    foreach ($processes as $process) {
        $process->wait();
    }

    $succeeded = collect($processes)->filter(fn (Process $p): bool => $p->getExitCode() === 0)->count();

    $balance = StockBalance::withoutGlobalScopes()
        ->where('warehouse_id', $warehouse->getKey())
        ->where('product_id', $product->getKey())
        ->first();

    expect((string) $balance->quantity_on_hand)->toBe('0.0000')   // never oversold below zero
        ->and($succeeded)->toBe(5);                               // exactly the 5 available succeeded
});
