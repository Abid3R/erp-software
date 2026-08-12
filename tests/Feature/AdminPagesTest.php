<?php

use App\Models\Company;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** Every admin surface must render for an authorised company member. */
dataset('adminPages', [
    'chart of accounts' => '/admin/accounts',
    'fixed assets' => '/admin/fixed-assets',
    'reports' => '/admin/reports',
    'approvals inbox' => '/admin/approval-requests',
    'audit log' => '/admin/audit-logs',
    'customers' => '/admin/customers',
    'suppliers' => '/admin/suppliers',
    'leads' => '/admin/leads',
    'opportunities' => '/admin/opportunities',
    'pipeline' => '/admin/pipeline',
    'stock levels' => '/admin/stocks',
    'stock adjustments' => '/admin/stock-adjustments',
    'stock transfers' => '/admin/stock-transfers',
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
    'requisitions' => '/admin/purchase-requisitions',
    'rfqs' => '/admin/rfqs',
    'purchase orders' => '/admin/purchase-orders',
    'buying prices' => '/admin/buying-prices',
    'purchase returns' => '/admin/purchase-returns',
    'sales orders' => '/admin/sales-orders',
    'quotations' => '/admin/quotations',
    'sales returns' => '/admin/sales-returns',
    'mrp' => '/admin/mrp',
    'users' => '/admin/users',
    'report settings' => '/admin/report-settings',
    'document library' => '/admin/documents',
]);

it('renders admin page for an authorised user', function (string $path) {
    $company = Company::factory()->create();
    $user = superAdminFor($company);

    $this->actingAs($user)->get($path)->assertOk();
})->with('adminPages');
