<?php

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Payment;
use App\Models\ReportSetting;
use App\Models\Supplier;
use App\Support\CompanyContext;
use Spatie\Permission\Models\Permission;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('gates the report settings page to holders of its permission', function () {
    $company = Company::factory()->create();
    Permission::findOrCreate('page_ReportSettings');

    $editor = memberWithRoles($company, 'editor');
    $editor->givePermissionTo('page_ReportSettings');
    $this->actingAs($editor)->get('/admin/report-settings')->assertOk();

    $roleless = memberWithRoles($company);
    $this->actingAs($roleless)->get('/admin/report-settings')->assertForbidden();
});

it('applies a company\'s branding to its printed vouchers', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    ReportSetting::create([
        'company_id' => $company->getKey(),
        'show_logo' => true,
        'footer_note' => 'Thank you — Dhaka Traders',
        'signatory_left' => 'Cashier',
        'signatory_right' => 'Manager',
    ]);

    $supplier = Supplier::factory()->create(['company_id' => $company->getKey(), 'name' => 'Vendor']);
    $payment = Payment::create([
        'company_id' => $company->getKey(),
        'direction' => PaymentDirection::Payment,
        'party_type' => $supplier->getMorphClass(),
        'party_id' => $supplier->getKey(),
        'date' => '2026-06-01',
        'amount' => '1200',
        'method' => PaymentMethod::Cash,
        'idempotency_key' => 'BRAND-1',
    ]);

    $user = superAdminFor($company);

    $this->actingAs($user)->get(route('print.payment', $payment))
        ->assertOk()
        ->assertSee('Thank you — Dhaka Traders')
        ->assertSee('Cashier')
        ->assertSee('Manager');
});
