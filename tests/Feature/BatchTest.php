<?php

use App\Domain\Manufacturing\BatchTrace;
use App\Models\Batch;
use App\Models\BatchConsumption;
use App\Models\Company;
use App\Models\Product;
use App\Models\Unit;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function batchProduct(Company $company): Product
{
    $unit = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);

    return Product::create([
        'unit_id' => $unit->getKey(), 'sku' => 'YARN-'.uniqid(), 'name' => 'Yarn',
        'cost_price' => 10, 'selling_price' => 0,
    ]);
}

it('auto-generates unique, sequential batch numbers per company', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $product = batchProduct($company);

    $a = Batch::create(['product_id' => $product->getKey(), 'quantity' => 100]);
    $b = Batch::create(['product_id' => $product->getKey(), 'quantity' => 50]);

    expect($a->batch_number)->toBe('B-000001')
        ->and($b->batch_number)->toBe('B-000002')
        ->and($a->batch_number)->not->toBe($b->batch_number);
});

it('restarts batch numbering per company', function () {
    $companyA = Company::factory()->create();
    app(CompanyContext::class)->set($companyA);
    $first = Batch::create(['product_id' => batchProduct($companyA)->getKey(), 'quantity' => 10]);

    $companyB = Company::factory()->create();
    app(CompanyContext::class)->set($companyB);
    $second = Batch::create(['product_id' => batchProduct($companyB)->getKey(), 'quantity' => 10]);

    expect($first->batch_number)->toBe('B-000001')
        ->and($second->batch_number)->toBe('B-000001'); // separate counters
});

it('traces a batch back to the inputs it was made from', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $product = batchProduct($company);

    $yarn = Batch::create(['product_id' => $product->getKey(), 'quantity' => 100]);
    $grey = Batch::create(['product_id' => $product->getKey(), 'quantity' => 95]);

    BatchConsumption::create([
        'output_batch_id' => $grey->getKey(), 'input_batch_id' => $yarn->getKey(), 'quantity' => 100,
    ]);

    expect($grey->consumedBatches->pluck('id'))->toContain($yarn->getKey())
        ->and($yarn->usedInBatches->pluck('id'))->toContain($grey->getKey());
});

it('traces a multi-stage lineage both ways (yarn -> grey -> dyed)', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $product = batchProduct($company);

    $yarn = Batch::create(['product_id' => $product->getKey(), 'quantity' => 100]);
    $grey = Batch::create(['product_id' => $product->getKey(), 'quantity' => 95]);
    $dyed = Batch::create(['product_id' => $product->getKey(), 'quantity' => 90]);

    BatchConsumption::create(['output_batch_id' => $grey->getKey(), 'input_batch_id' => $yarn->getKey(), 'quantity' => 100]);
    BatchConsumption::create(['output_batch_id' => $dyed->getKey(), 'input_batch_id' => $grey->getKey(), 'quantity' => 95]);

    // Backward from dyed: reaches grey (depth 0) then yarn (depth 1).
    $origin = collect(BatchTrace::origin($dyed))->map(fn ($r) => $r['batch']->getKey());
    expect($origin)->toContain($grey->getKey())->toContain($yarn->getKey());

    // Forward from yarn: reaches grey (depth 0) then dyed (depth 1).
    $usage = collect(BatchTrace::usage($yarn))->map(fn ($r) => $r['batch']->getKey());
    expect($usage)->toContain($grey->getKey())->toContain($dyed->getKey());
});
