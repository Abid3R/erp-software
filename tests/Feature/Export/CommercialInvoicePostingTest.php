<?php

use App\Actions\Export\CancelCommercialInvoice;
use App\Actions\Export\PostCommercialInvoice;
use App\Enums\AccountType;
use App\Enums\CommercialInvoiceStatus;
use App\Enums\PeriodStatus;
use App\Exceptions\ExportException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\CommercialInvoice;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Journal;
use App\Models\Product;
use App\Models\Unit;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** Company with an open period, the AR/Sales/VAT accounts, and an approved USD commercial invoice. */
function ciSetup(float $unitPrice = 100, float $rate = 110, float $qty = 10, ?string $taxCode = null): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    foreach ([['1100', 'Receivable', AccountType::Asset], ['4000', 'Sales', AccountType::Revenue], ['2200', 'Output VAT', AccountType::Liability]] as [$code, $name, $type]) {
        Account::create(['company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type]);
    }

    $unit = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $product = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'FAB-1', 'name' => 'Fabric', 'cost_price' => 0, 'selling_price' => 0]);
    $customer = Customer::factory()->create(['company_id' => $company->getKey()]);

    $ci = CommercialInvoice::create([
        'company_id' => $company->getKey(), 'invoice_date' => '2026-06-01', 'customer_id' => $customer->getKey(),
        'currency_code' => 'USD', 'exchange_rate' => $rate, 'status' => CommercialInvoiceStatus::Approved,
    ]);
    $ci->lines()->create(['product_id' => $product->getKey(), 'quantity' => $qty, 'unit_price' => $unitPrice]);

    return [$company, $ci->refresh()->load('lines'), $customer];
}

it('posts a balanced AR journal in the base currency', function () {
    [$company, $ci] = ciSetup(unitPrice: 100, rate: 110, qty: 10); // 1000 USD × 110 = 110,000 base

    $posted = app(PostCommercialInvoice::class)->handle($ci);

    expect($posted->status)->toBe(CommercialInvoiceStatus::Posted)
        ->and($posted->journal_id)->not->toBeNull();

    $journal = Journal::withoutGlobalScopes()->with('lines.account')->find($posted->journal_id);
    $debit = $journal->lines->reduce(fn (BigDecimal $c, $l) => $c->plus($l->debit), BigDecimal::zero());
    $credit = $journal->lines->reduce(fn (BigDecimal $c, $l) => $c->plus($l->credit), BigDecimal::zero());

    expect((string) $debit)->toBe('110000.00')
        ->and($debit->isEqualTo($credit))->toBeTrue();

    $receivable = $journal->lines->firstWhere(fn ($l) => $l->account->code === '1100');
    $sales = $journal->lines->firstWhere(fn ($l) => $l->account->code === '4000');
    expect((string) $receivable->debit)->toBe('110000.00')
        ->and((string) $sales->credit)->toBe('110000.00');
});

it('is idempotent — a second post is rejected, leaving one journal', function () {
    [$company, $ci] = ciSetup();

    app(PostCommercialInvoice::class)->handle($ci);

    expect(fn () => app(PostCommercialInvoice::class)->handle($ci->refresh()))
        ->toThrow(ExportException::class)
        ->and(Journal::withoutGlobalScopes()->where('company_id', $company->getKey())->count())->toBe(1);
});

it('will not post an unapproved (draft) invoice', function () {
    [, $ci] = ciSetup();
    $ci->update(['status' => CommercialInvoiceStatus::Draft]);

    app(PostCommercialInvoice::class)->handle($ci->refresh());
})->throws(ExportException::class);

it('reverses the AR journal when a posted invoice is cancelled', function () {
    [$company, $ci] = ciSetup();
    app(PostCommercialInvoice::class)->handle($ci);

    app(CancelCommercialInvoice::class)->handle($ci->refresh(), 'buyer cancelled');

    expect($ci->refresh()->status)->toBe(CommercialInvoiceStatus::Cancelled);

    // Original + reversal → net movement on every account is zero.
    $lines = Journal::withoutGlobalScopes()->where('company_id', $company->getKey())->with('lines')->get()->flatMap->lines;
    $net = $lines->reduce(fn (BigDecimal $c, $l) => $c->plus($l->debit)->minus($l->credit), BigDecimal::zero());
    expect((string) $net)->toBe('0.00')
        ->and(Journal::withoutGlobalScopes()->where('company_id', $company->getKey())->count())->toBe(2);
});

it('books output VAT to its own account when the invoice is taxed', function () {
    [$company, $ci, $customer] = ciSetup();
    $tax = App\Models\TaxRate::create([
        'company_id' => $company->getKey(), 'code' => 'VAT15', 'name' => 'VAT 15%', 'rate_percent' => 15,
        'is_inclusive' => false, 'effective_from' => '2026-01-01', 'is_active' => true,
    ]);
    $ci->update(['tax_rate_id' => $tax->getKey()]);

    $posted = app(PostCommercialInvoice::class)->handle($ci->refresh()->load('lines', 'taxRate'));
    $journal = Journal::withoutGlobalScopes()->with('lines.account')->find($posted->journal_id);

    $vat = $journal->lines->firstWhere(fn ($l) => $l->account->code === '2200');
    // 1000 USD × 15% = 150 USD × 110 = 16,500 base.
    expect((string) $vat->credit)->toBe('16500.00');
});
