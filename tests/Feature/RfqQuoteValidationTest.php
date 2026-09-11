<?php

use App\Enums\RfqStatus;
use App\Filament\Resources\RfqResource\Pages\EditRfq;
use App\Filament\Resources\RfqResource\RelationManagers\QuotesRelationManager;
use App\Models\Company;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\RfqLine;
use App\Models\RfqQuote;
use App\Models\Supplier;
use App\Models\Unit;
use App\Support\CompanyContext;
use Livewire\Livewire;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('rejects a duplicate supplier quote on the same RFQ line with a validation error', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $this->actingAs(superAdminFor($company));

    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = \App\Models\Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $product = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'DYE', 'name' => 'Dye', 'cost_price' => 0, 'selling_price' => 0]);
    $supplier = Supplier::factory()->create(['company_id' => $company->getKey()]);
    $rfq = Rfq::create(['number' => 'RFQ-1', 'warehouse_id' => $wh->getKey(), 'status' => RfqStatus::Sent]);
    $line = $rfq->lines()->create(['product_id' => $product->getKey(), 'quantity' => 100]);
    $rfq->quotes()->create(['rfq_line_id' => $line->getKey(), 'supplier_id' => $supplier->getKey(), 'unit_price' => 50]);

    // Adding a second quote for the same (line, supplier) must fail validation, not 500.
    Livewire::test(QuotesRelationManager::class, ['ownerRecord' => $rfq, 'pageClass' => EditRfq::class])
        ->callTableAction('create', data: [
            'rfq_line_id' => $line->getKey(),
            'supplier_id' => $supplier->getKey(),
            'unit_price' => 55,
        ])
        ->assertHasTableActionErrors(['supplier_id']);

    expect(RfqQuote::query()->count())->toBe(1);
});

it('allows a different supplier to quote the same line', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $this->actingAs(superAdminFor($company));

    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = \App\Models\Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $product = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'DYE', 'name' => 'Dye', 'cost_price' => 0, 'selling_price' => 0]);
    $s1 = Supplier::factory()->create(['company_id' => $company->getKey()]);
    $s2 = Supplier::factory()->create(['company_id' => $company->getKey()]);
    $rfq = Rfq::create(['number' => 'RFQ-2', 'warehouse_id' => $wh->getKey(), 'status' => RfqStatus::Sent]);
    $line = $rfq->lines()->create(['product_id' => $product->getKey(), 'quantity' => 100]);
    $rfq->quotes()->create(['rfq_line_id' => $line->getKey(), 'supplier_id' => $s1->getKey(), 'unit_price' => 50]);

    Livewire::test(QuotesRelationManager::class, ['ownerRecord' => $rfq, 'pageClass' => EditRfq::class])
        ->callTableAction('create', data: [
            'rfq_line_id' => $line->getKey(),
            'supplier_id' => $s2->getKey(),
            'unit_price' => 48,
        ])
        ->assertHasNoTableActionErrors();

    expect(RfqQuote::query()->count())->toBe(2);
});
