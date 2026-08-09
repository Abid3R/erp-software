<?php

use App\Domain\Accounting\TrialBalance;
use App\Domain\Reporting\BalanceSheet;
use App\Domain\Reporting\ProfitAndLoss;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Supplier;
use App\Support\CompanyContext;
use Barryvdh\DomPDF\Facade\Pdf;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('generates a financial-statements PDF', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $pdf = Pdf::loadView('reports.pdf', [
        'report' => [
            'pl' => ProfitAndLoss::for($company->getKey()),
            'bs' => BalanceSheet::for($company->getKey()),
            'tb' => TrialBalance::totals($company->getKey()),
        ],
        'company' => $company,
        'from' => null,
        'to' => null,
    ]);

    expect(substr($pdf->output(), 0, 4))->toBe('%PDF');
});

it('generates a payment voucher PDF (with amount in words)', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $supplier = Supplier::factory()->create(['company_id' => $company->getKey()]);

    $payment = Payment::create([
        'company_id' => $company->getKey(),
        'direction' => PaymentDirection::Payment,
        'party_type' => $supplier->getMorphClass(),
        'party_id' => $supplier->getKey(),
        'date' => '2026-06-01',
        'amount' => '50000',
        'method' => PaymentMethod::Bank,
        'idempotency_key' => 'PDF-TEST-1',
    ]);

    $pdf = Pdf::loadView('reports.payment-pdf', [
        'payment' => $payment->load('party'),
        'company' => $company,
    ]);

    expect(substr($pdf->output(), 0, 4))->toBe('%PDF');
});
