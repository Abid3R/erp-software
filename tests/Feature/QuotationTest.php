<?php

use App\Actions\Sales\ConvertQuotationToOrder;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Exceptions\QuotationException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Quotation, 1: Product} */
function quotationSetup(string $status = 'sent'): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $customer = Customer::create(['company_id' => $company->getKey(), 'name' => 'Buyer', 'code' => 'B1']);
    $warehouse = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create(['company_id' => $company->getKey(), 'unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'P', 'cost_price' => 0, 'selling_price' => 0]);

    $quotation = Quotation::create([
        'number' => 'QT-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'quote_date' => '2026-06-01', 'valid_until' => '2026-06-30', 'status' => $status,
    ]);
    $quotation->lines()->create(['product_id' => $product->getKey(), 'quantity' => 30, 'unit_price' => 420]);

    return [$quotation, $product];
}

it('totals a quotation from its lines', function () {
    [$quotation] = quotationSetup();

    expect((string) $quotation->load('lines')->total()->toScale(2))->toBe('12600.00'); // 30 × 420
});

it('converts an accepted quotation into a confirmed sales order', function () {
    [$quotation, $product] = quotationSetup('accepted');

    $order = app(ConvertQuotationToOrder::class)->handle($quotation);

    expect($order->status)->toBe(SalesOrderStatus::Confirmed)
        ->and($order->so_number)->toBe('SO-QT-1')
        ->and($order->customer_id)->toBe($quotation->customer_id)
        ->and($order->lines)->toHaveCount(1)
        ->and((string) $order->lines->first()->quantity_ordered)->toBe('30.0000')
        ->and((string) $order->lines->first()->product_id)->toBe((string) $product->getKey())
        ->and($quotation->refresh()->status)->toBe(QuotationStatus::Converted);
});

it('refuses to convert a draft quotation', function () {
    [$quotation] = quotationSetup('draft');

    expect(fn () => app(ConvertQuotationToOrder::class)->handle($quotation))
        ->toThrow(QuotationException::class);
});

it('refuses to convert an already converted quotation twice', function () {
    [$quotation] = quotationSetup('accepted');
    app(ConvertQuotationToOrder::class)->handle($quotation);

    expect(fn () => app(ConvertQuotationToOrder::class)->handle($quotation->refresh()))
        ->toThrow(QuotationException::class);
});
