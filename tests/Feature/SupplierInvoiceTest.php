<?php

use App\Actions\Purchasing\PostSupplierInvoice;
use App\Domain\Accounting\PartyLedger;
use App\Domain\Accounting\TrialBalance;
use App\Domain\Reporting\AccountBalances;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Exceptions\SupplierInvoiceException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\TaxRate;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: Supplier, 2: array<string, Account>} */
function supplierInvoiceSetup(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    $make = fn (string $code, string $name, AccountType $type) => Account::create([
        'company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type,
    ]);
    $accounts = [
        'input_vat' => $make('1250', 'Input VAT', AccountType::Asset),
        'grni' => $make('2100', 'GRNI', AccountType::Liability),
        'payable' => $make('2000', 'Accounts Payable', AccountType::Liability),
    ];
    $supplier = Supplier::factory()->create(['company_id' => $company->getKey()]);

    return [$company, $supplier, $accounts];
}

it('posts a supplier invoice with VAT: clears GRNI, adds input VAT, credits AP', function () {
    [$company, $supplier, $accounts] = supplierInvoiceSetup();
    TaxRate::create(['code' => 'VAT15', 'name' => 'VAT 15%', 'rate_percent' => '15', 'effective_from' => '2026-01-01']);

    $inv = SupplierInvoice::create([
        'number' => 'SINV-1', 'supplier_id' => $supplier->getKey(), 'invoice_date' => '2026-06-05', 'status' => 'draft',
    ]);
    $inv->lines()->create(['account_id' => $accounts['grni']->getKey(), 'amount' => 1000, 'tax_code' => 'VAT15']);

    $posted = app(PostSupplierInvoice::class)->handle($inv);

    // Dr GRNI 1000 + Dr Input VAT 150 / Cr AP 1150.
    expect($posted->status)->toBe(SupplierInvoiceStatus::Posted)
        ->and((string) PartyLedger::payable($supplier))->toBe('1150.00')
        ->and((string) AccountBalances::netForAccount($accounts['input_vat']))->toBe('150.00')
        ->and((string) AccountBalances::netForAccount($accounts['grni']))->toBe('-1000.00') // liability cleared (debited)
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});

it('posts a supplier invoice with no tax code (net = gross)', function () {
    [$company, $supplier, $accounts] = supplierInvoiceSetup();

    $inv = SupplierInvoice::create([
        'number' => 'SINV-2', 'supplier_id' => $supplier->getKey(), 'invoice_date' => '2026-06-05', 'status' => 'draft',
    ]);
    $inv->lines()->create(['account_id' => $accounts['grni']->getKey(), 'amount' => 800]);

    app(PostSupplierInvoice::class)->handle($inv);

    expect((string) PartyLedger::payable($supplier))->toBe('800.00')
        ->and((string) AccountBalances::netForAccount($accounts['input_vat']))->toBe('0.00')
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});

it('refuses to post an already posted supplier invoice', function () {
    [, $supplier, $accounts] = supplierInvoiceSetup();
    $inv = SupplierInvoice::create([
        'number' => 'SINV-3', 'supplier_id' => $supplier->getKey(), 'invoice_date' => '2026-06-05', 'status' => 'draft',
    ]);
    $inv->lines()->create(['account_id' => $accounts['grni']->getKey(), 'amount' => 100]);
    app(PostSupplierInvoice::class)->handle($inv);

    expect(fn () => app(PostSupplierInvoice::class)->handle($inv->refresh()))->toThrow(SupplierInvoiceException::class);
});
