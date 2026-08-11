<?php

use App\Models\Company;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** Every admin surface must render for an authorised company member. */
dataset('adminPages', [
    'chart of accounts' => '/admin/accounts',
    'reports' => '/admin/reports',
    'approvals inbox' => '/admin/approval-requests',
    'audit log' => '/admin/audit-logs',
    'customers' => '/admin/customers',
    'suppliers' => '/admin/suppliers',
    'stock levels' => '/admin/stocks',
    'journals' => '/admin/journals',
    'general ledger' => '/admin/general-ledger',
    'payments' => '/admin/payments',
    'employees' => '/admin/employees',
    'departments' => '/admin/departments',
    'designations' => '/admin/designations',
    'shifts' => '/admin/shifts',
    'attendance' => '/admin/attendances',
    'rosters' => '/admin/rosters',
    'leave types' => '/admin/leave-types',
    'leave requests' => '/admin/leave-requests',
    'payroll' => '/admin/payroll-runs',
    'holidays' => '/admin/holidays',
    'bills of materials' => '/admin/boms',
    'manufacturing orders' => '/admin/manufacturing-orders',
    'purchase orders' => '/admin/purchase-orders',
    'sales orders' => '/admin/sales-orders',
    'users' => '/admin/users',
    'report settings' => '/admin/report-settings',
]);

it('renders admin page for an authorised user', function (string $path) {
    $company = Company::factory()->create();
    $user = superAdminFor($company);

    $this->actingAs($user)->get($path)->assertOk();
})->with('adminPages');
