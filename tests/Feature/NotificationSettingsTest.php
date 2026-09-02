<?php

use App\Actions\Accounting\PostJournal;
use App\Actions\Notifications\SendLowStockAlerts;
use App\Actions\Notifications\SendOverdueInvoiceAlerts;
use App\Domain\Accounting\JournalDraft;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\NotificationSetting;
use App\Models\Product;
use App\Models\Unit;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('alerts inventory & purchasing when a product is at or below reorder level', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $inventory = memberWithRoles($company, 'inventory');
    $outsider = memberWithRoles($company); // no role

    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    // No stock rows => on-hand 0, which is at/below the reorder level of 10.
    Product::create([
        'unit_id' => $unit->getKey(), 'sku' => 'LOW-1', 'name' => 'Low item',
        'cost_price' => 10, 'selling_price' => 20, 'reorder_level' => 10, 'is_active' => true,
    ]);

    $flagged = app(SendLowStockAlerts::class)->forCompany($company->getKey());

    expect($flagged)->toBe(1)
        ->and($inventory->notifications()->count())->toBe(1)
        ->and($outsider->notifications()->count())->toBe(0);
});

it('does not send low-stock alerts when the toggle is off', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $inventory = memberWithRoles($company, 'inventory');
    NotificationSetting::create(['company_id' => $company->getKey(), 'low_stock_enabled' => false]);

    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    Product::create([
        'unit_id' => $unit->getKey(), 'sku' => 'LOW-2', 'name' => 'Low item',
        'cost_price' => 10, 'selling_price' => 20, 'reorder_level' => 10, 'is_active' => true,
    ]);

    expect(app(SendLowStockAlerts::class)->forCompany($company->getKey()))->toBe(0)
        ->and($inventory->notifications()->count())->toBe(0);
});

it('alerts accounting when a customer has receivables overdue past the threshold', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    $ar = Account::create(['company_id' => $company->getKey(), 'code' => '1100', 'name' => 'AR', 'type' => AccountType::Asset]);
    $sales = Account::create(['company_id' => $company->getKey(), 'code' => '4000', 'name' => 'Sales', 'type' => AccountType::Revenue]);
    $customer = Customer::factory()->create(['company_id' => $company->getKey()]);

    $accounting = memberWithRoles($company, 'accounting');

    // An unpaid invoice dated well over 30 days ago is overdue.
    app(PostJournal::class)->handle(
        JournalDraft::make('2026-01-15')->debit($ar, '5000', party: $customer)->credit($sales, '5000'),
    );

    $flagged = app(SendOverdueInvoiceAlerts::class)->forCompany($company->getKey());

    expect($flagged)->toBe(1)
        ->and($accounting->notifications()->count())->toBe(1);
});

it('does not send overdue alerts when the toggle is off', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    NotificationSetting::create(['company_id' => $company->getKey(), 'overdue_invoices_enabled' => false]);

    $accounting = memberWithRoles($company, 'accounting');

    expect(app(SendOverdueInvoiceAlerts::class)->forCompany($company->getKey()))->toBe(0)
        ->and($accounting->notifications()->count())->toBe(0);
});

it('suppresses the leave-request alert when leave approvals are disabled', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    NotificationSetting::create(['company_id' => $company->getKey(), 'leave_approvals_enabled' => false]);

    $hr = memberWithRoles($company, 'hr');

    $type = LeaveType::create(['name' => 'Annual', 'code' => 'ANNUAL', 'annual_quota' => 20]);
    $employee = Employee::create([
        'employee_code' => 'E1', 'first_name' => 'Sara',
        'employment_type' => 'permanent', 'status' => 'active', 'join_date' => '2025-01-01',
    ]);

    LeaveRequest::create([
        'employee_id' => $employee->getKey(), 'leave_type_id' => $type->getKey(),
        'start_date' => '2026-09-10', 'end_date' => '2026-09-11', 'status' => 'pending',
    ]);

    expect($hr->notifications()->count())->toBe(0);
});

it('loads the notification settings page for an authorised user', function () {
    $company = Company::factory()->create();
    $user = superAdminFor($company);

    $this->actingAs($user)->get('/admin/notification-settings')->assertOk();
});
