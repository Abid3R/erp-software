<?php

use App\Actions\Purchasing\AwardRfq;
use App\Actions\Purchasing\CreateRfqFromRequisition;
use App\Domain\Purchasing\ComparativeStatement;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RequisitionStatus;
use App\Enums\RfqStatus;
use App\Exceptions\SourcingException;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseRequisition;
use App\Models\Rfq;
use App\Models\RfqQuote;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: Warehouse, 2: Product, 3: Supplier, 4: Supplier} */
function sourcingSetup(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'WH1']);
    $product = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'Widget', 'cost_price' => 0, 'selling_price' => 0]);
    $alpha = Supplier::create(['name' => 'Alpha', 'code' => 'A']);
    $beta = Supplier::create(['name' => 'Beta', 'code' => 'B']);

    return [$company, $warehouse, $product, $alpha, $beta];
}

function approvedRequisition(Product $product): PurchaseRequisition
{
    $req = PurchaseRequisition::create(['number' => 'REQ-1', 'status' => 'approved']);
    $req->lines()->create(['product_id' => $product->getKey(), 'quantity' => 100]);

    return $req;
}

it('creates an RFQ from an approved requisition and marks it converted', function () {
    [, $warehouse, $product] = sourcingSetup();
    $req = approvedRequisition($product);

    $rfq = app(CreateRfqFromRequisition::class)->handle($req, (int) $warehouse->getKey());

    expect($rfq->status)->toBe(RfqStatus::Sent)
        ->and($rfq->lines()->count())->toBe(1)
        ->and((string) $rfq->lines()->first()->quantity)->toBe('100.0000')
        ->and($req->fresh()->status)->toBe(RequisitionStatus::Converted);
});

it('will not create an RFQ from a non-approved requisition', function () {
    [, $warehouse, $product] = sourcingSetup();
    $req = approvedRequisition($product);
    $req->update(['status' => 'draft']);

    expect(fn () => app(CreateRfqFromRequisition::class)->handle($req->fresh(), (int) $warehouse->getKey()))
        ->toThrow(SourcingException::class);
});

it('builds a comparative statement highlighting the lowest quote', function () {
    [, $warehouse, $product, $alpha, $beta] = sourcingSetup();
    $rfq = app(CreateRfqFromRequisition::class)->handle(approvedRequisition($product), (int) $warehouse->getKey());
    $line = $rfq->lines()->first();
    RfqQuote::create(['rfq_id' => $rfq->getKey(), 'rfq_line_id' => $line->getKey(), 'supplier_id' => $alpha->getKey(), 'unit_price' => 320]);
    RfqQuote::create(['rfq_id' => $rfq->getKey(), 'rfq_line_id' => $line->getKey(), 'supplier_id' => $beta->getKey(), 'unit_price' => 310]);

    $statement = ComparativeStatement::for($rfq);

    expect($statement['lines'][0]['lowest_supplier_id'])->toBe((int) $beta->getKey())     // 310 < 320
        ->and($statement['lines'][0]['quotes'][$alpha->getKey()])->toBe('320.00')
        ->and($statement['lines'][0]['quotes'][$beta->getKey()])->toBe('310.00');

    $totals = collect($statement['suppliers'])->keyBy('id');
    expect($totals[$beta->getKey()]['total'])->toBe('31000.00');  // 100 × 310
});

it('awards the RFQ to a supplier, creating an approved PO from their quotes', function () {
    [, $warehouse, $product, $alpha, $beta] = sourcingSetup();
    $rfq = app(CreateRfqFromRequisition::class)->handle(approvedRequisition($product), (int) $warehouse->getKey());
    $line = $rfq->lines()->first();
    RfqQuote::create(['rfq_id' => $rfq->getKey(), 'rfq_line_id' => $line->getKey(), 'supplier_id' => $alpha->getKey(), 'unit_price' => 320]);
    RfqQuote::create(['rfq_id' => $rfq->getKey(), 'rfq_line_id' => $line->getKey(), 'supplier_id' => $beta->getKey(), 'unit_price' => 310]);

    $po = app(AwardRfq::class)->handle($rfq, (int) $beta->getKey());

    expect($po->status)->toBe(PurchaseOrderStatus::Approved)
        ->and($po->supplier_id)->toBe($beta->getKey())
        ->and($po->lines()->count())->toBe(1)
        ->and((string) $po->lines()->first()->unit_price)->toBe('310.0000')   // Beta's quote
        ->and((string) $po->lines()->first()->quantity_ordered)->toBe('100.0000')
        ->and($rfq->fresh()->status)->toBe(RfqStatus::Awarded);
});

it('cannot award to a supplier that did not quote', function () {
    [, $warehouse, $product, $alpha, $beta] = sourcingSetup();
    $rfq = app(CreateRfqFromRequisition::class)->handle(approvedRequisition($product), (int) $warehouse->getKey());
    $line = $rfq->lines()->first();
    RfqQuote::create(['rfq_id' => $rfq->getKey(), 'rfq_line_id' => $line->getKey(), 'supplier_id' => $alpha->getKey(), 'unit_price' => 320]);

    expect(fn () => app(AwardRfq::class)->handle($rfq, (int) $beta->getKey()))
        ->toThrow(SourcingException::class);
});
