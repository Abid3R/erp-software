<?php

use App\Actions\Export\AdvanceShipmentStatus;
use App\Actions\Export\GeneratePackingListFromInvoice;
use App\Enums\CommercialInvoiceStatus;
use App\Enums\ExportShipmentStatus;
use App\Enums\PackingListStatus;
use App\Exceptions\ExportException;
use App\Models\CommercialInvoice;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ExportShipment;
use App\Models\Product;
use App\Models\Unit;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function exportBase(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $customer = Customer::factory()->create(['company_id' => $company->getKey()]);

    return [$company, $customer];
}

it('advances a shipment through the allowed statuses', function () {
    [$company, $customer] = exportBase();
    $shipment = ExportShipment::create([
        'company_id' => $company->getKey(), 'customer_id' => $customer->getKey(),
        'shipment_date' => now(), 'status' => ExportShipmentStatus::Draft,
    ]);

    $advance = app(AdvanceShipmentStatus::class);
    $advance->handle($shipment, ExportShipmentStatus::ReadyForShipment);
    $advance->handle($shipment->refresh(), ExportShipmentStatus::Shipped);

    expect($shipment->refresh()->status)->toBe(ExportShipmentStatus::Shipped);
});

it('rejects an invalid backward shipment transition', function () {
    [$company, $customer] = exportBase();
    $shipment = ExportShipment::create([
        'company_id' => $company->getKey(), 'customer_id' => $customer->getKey(),
        'shipment_date' => now(), 'status' => ExportShipmentStatus::Shipped,
    ]);

    app(AdvanceShipmentStatus::class)->handle($shipment, ExportShipmentStatus::Draft);
})->throws(ExportException::class);

it('generates a packing list from a commercial invoice without re-entry', function () {
    [$company, $customer] = exportBase();
    $unit = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $p1 = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'A', 'name' => 'Item A', 'cost_price' => 0, 'selling_price' => 0]);
    $p2 = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'B', 'name' => 'Item B', 'cost_price' => 0, 'selling_price' => 0]);

    $ci = CommercialInvoice::create([
        'company_id' => $company->getKey(), 'invoice_date' => now(), 'customer_id' => $customer->getKey(),
        'currency_code' => 'USD', 'exchange_rate' => 110, 'status' => CommercialInvoiceStatus::Approved,
    ]);
    $ci->lines()->create(['product_id' => $p1->getKey(), 'quantity' => 100, 'unit_price' => 5]);
    $ci->lines()->create(['product_id' => $p2->getKey(), 'quantity' => 60, 'unit_price' => 8]);

    $pl = app(GeneratePackingListFromInvoice::class)->handle($ci->refresh()->load('lines.product'));

    expect($pl->status)->toBe(PackingListStatus::Draft)
        ->and($pl->commercial_invoice_id)->toBe($ci->getKey())
        ->and($pl->customer_id)->toBe($customer->getKey())
        ->and($pl->lines)->toHaveCount(2)
        ->and((string) $pl->lines->first()->quantity)->toBe('100.0000');
});
