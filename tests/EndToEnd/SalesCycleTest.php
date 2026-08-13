<?php

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Payments\RecordCustomerReceipt;
use App\Actions\Sales\ConvertQuotationToOrder;
use App\Actions\Sales\FulfillSalesOrder;
use App\Domain\Accounting\PartyLedger;
use App\Domain\Accounting\TrialBalance;
use App\Domain\Reporting\AccountBalances;
use App\Domain\Reporting\PartyAgingDetail;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\PeriodStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/**
 * Full sales cycle end-to-end: Quotation -> Sales Order -> Fulfill (Delivery +
 * Invoice + AR + COGS) -> Customer Receipt (AR cleared). Every step asserts the
 * user-visible business state (inventory, AR, sales, COGS, cash, aging), and the
 * trial balance stays balanced throughout.
 */
function salesCycleSetup(): array
{
    $company = Company::factory()->create(['allow_negative_stock' => false]);
    app(CompanyContext::class)->set($company);
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    $mk = fn (string $code, string $name, AccountType $type) => Account::create([
        'company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type,
    ]);
    $accounts = [
        'cash' => $mk('1000', 'Cash', AccountType::Asset),
        'bank' => $mk('1010', 'Bank', AccountType::Asset),
        'receivable' => $mk('1100', 'Accounts Receivable', AccountType::Asset),
        'inventory' => $mk('1200', 'Inventory', AccountType::Asset),
        'payable' => $mk('2000', 'Accounts Payable', AccountType::Liability),
        'grni' => $mk('2100', 'GRNI', AccountType::Liability),
        'sales' => $mk('4000', 'Sales Revenue', AccountType::Revenue),
        'sales_returns' => $mk('4100', 'Sales Returns', AccountType::Revenue),
        'cogs' => $mk('5000', 'COGS', AccountType::Expense),
    ];

    $customer = Customer::create(['name' => 'Acme Retail', 'code' => 'CUST-1']);
    $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    // Cost 320, will sell for 420 -> Tk 100 gross profit per unit.
    $product = Product::create([
        'unit_id' => $unit->getKey(), 'sku' => 'PAP-A4', 'name' => 'A4 Paper',
        'cost_price' => 320, 'selling_price' => 420,
    ]);
    // Seed 100 units into stock at Tk 320 average cost (from a prior GRN).
    app(ReceiveStock::class)->handle($warehouse, $product, '100', '320');

    return [$company, $customer, $warehouse, $product, $accounts];
}

it('walks Quotation -> SO -> Fulfill -> Receipt with correct inventory, AR, COGS, aging', function () {
    [$company, $customer, $warehouse, $product, $accounts] = salesCycleSetup();
    $companyId = $company->getKey();

    // ---------------- 1. QUOTATION (Sent) ----------------
    $quotation = Quotation::create([
        'number' => 'QT-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'quote_date' => '2026-06-01', 'valid_until' => '2026-06-30', 'status' => 'sent',
    ]);
    $quotation->lines()->create(['product_id' => $product->getKey(), 'quantity' => 60, 'unit_price' => 420]);

    // Quoting doesn't touch inventory or the ledger.
    expect((string) StockBalance::query()->firstOrFail()->quantity_on_hand)->toBe('100.0000')
        ->and((string) PartyLedger::receivable($customer))->toBe('0.00')
        ->and(TrialBalance::isBalanced($companyId))->toBeTrue();

    // ---------------- 2. CONVERT TO SALES ORDER ----------------
    $order = app(ConvertQuotationToOrder::class)->handle($quotation);
    expect($quotation->refresh()->status)->toBe(QuotationStatus::Converted)
        ->and($order->status)->toBe(SalesOrderStatus::Confirmed)
        ->and($order->so_number)->toBe('SO-QT-1')
        ->and((string) $order->lines->first()->quantity_ordered)->toBe('60.0000')
        // Confirming an order still does not touch stock or the ledger.
        ->and((string) StockBalance::query()->firstOrFail()->quantity_on_hand)->toBe('100.0000')
        ->and((string) PartyLedger::receivable($customer))->toBe('0.00');

    // ---------------- 3. PARTIAL DELIVERY (25 of 60) ----------------
    $lineId = $order->lines->first()->getKey();
    $order = app(FulfillSalesOrder::class)->handle($order, [$lineId => '25'], '2026-06-05');

    // Partial delivery: SO moves to PartiallyDelivered; stock, AR, sales and COGS all move.
    expect($order->status)->toBe(SalesOrderStatus::PartiallyDelivered)
        ->and((string) StockBalance::query()->firstOrFail()->quantity_on_hand)->toBe('75.0000') // 100 - 25
        ->and((string) PartyLedger::receivable($customer))->toBe('10500.00')                     // 25 × 420
        ->and((string) AccountBalances::netForAccount($accounts['sales']))->toBe('10500.00')    // revenue (Cr)
        ->and((string) AccountBalances::netForAccount($accounts['cogs']))->toBe('8000.00')       // 25 × 320
        ->and(TrialBalance::isBalanced($companyId))->toBeTrue();

    // ---------------- 4. FINAL DELIVERY (remaining 35) ----------------
    $order = app(FulfillSalesOrder::class)->handle($order->refresh(), [$lineId => '35'], '2026-06-10');

    expect($order->status)->toBe(SalesOrderStatus::Delivered)
        ->and((string) StockBalance::query()->firstOrFail()->quantity_on_hand)->toBe('40.0000')  // 100 - 60
        ->and((string) PartyLedger::receivable($customer))->toBe('25200.00')                     // 60 × 420
        ->and((string) AccountBalances::netForAccount($accounts['sales']))->toBe('25200.00')
        ->and((string) AccountBalances::netForAccount($accounts['cogs']))->toBe('19200.00')      // 60 × 320
        ->and(TrialBalance::isBalanced($companyId))->toBeTrue();

    // Each delivery posts its own AR journal, so the aging shows two open documents
    // (the 25-unit delivery and the 35-unit delivery), together the full 25,200.
    $aging = PartyAgingDetail::receivables($companyId, '2026-06-30');
    expect($aging['rows'])->toHaveCount(2)
        ->and($aging['totals']['total'])->toBe('25200.00');

    // ---------------- 5. PARTIAL RECEIPT (Tk 10,000 by bank) ----------------
    app(RecordCustomerReceipt::class)->handle($customer, '10000', PaymentMethod::Bank, '2026-06-15', 'RCPT-1');

    expect((string) PartyLedger::receivable($customer))->toBe('15200.00')                        // 25200 - 10000
        ->and((string) AccountBalances::netForAccount($accounts['bank']))->toBe('10000.00')
        ->and(TrialBalance::isBalanced($companyId))->toBeTrue();

    // AR aging: the receipt is FIFO-applied to the oldest invoice first — it fully
    // pays that one (10,500) and part of the next (leaving 14,700 outstanding).
    $aging = PartyAgingDetail::receivables($companyId, '2026-06-30');
    expect($aging['totals']['total'])->toBe('15200.00');

    // ---------------- 6. FINAL RECEIPT (Tk 15,200 by cash) ----------------
    app(RecordCustomerReceipt::class)->handle($customer, '15200', PaymentMethod::Cash, '2026-06-20', 'RCPT-2');

    // AR fully cleared; cash + bank hold the full invoice value; TB balanced.
    expect((string) PartyLedger::receivable($customer))->toBe('0.00')
        ->and((string) AccountBalances::netForAccount($accounts['cash']))->toBe('15200.00')
        ->and((string) AccountBalances::netForAccount($accounts['bank']))->toBe('10000.00')
        ->and(TrialBalance::isBalanced($companyId))->toBeTrue()
        // Fully settled -> the invoice drops off the aging.
        ->and(PartyAgingDetail::receivables($companyId, '2026-06-30')['rows'])->toHaveCount(0);
});
