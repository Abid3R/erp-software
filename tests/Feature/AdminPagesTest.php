<?php

use App\Models\Company;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** Every admin surface must render for an authorised company member. */
dataset('adminPages', [
    'chart of accounts' => '/admin/accounts',
    'fixed assets' => '/admin/fixed-assets',
    'expenses' => '/admin/expenses',
    'reports' => '/admin/reports',
    'approvals inbox' => '/admin/approval-requests',
    'audit log' => '/admin/audit-logs',
    'customers' => '/admin/customers',
    'suppliers' => '/admin/suppliers',
    'leads' => '/admin/leads',
    'opportunities' => '/admin/opportunities',
    'pipeline' => '/admin/pipeline',
    'stock levels' => '/admin/stocks',
    'stock valuation' => '/admin/stock-valuation',
    'stock ledger' => '/admin/stock-ledger',
    'stock adjustments' => '/admin/stock-adjustments',
    'stock transfers' => '/admin/stock-transfers',
    'journals' => '/admin/journals',
    'general ledger' => '/admin/general-ledger',
    'ar aging' => '/admin/receivables-aging',
    'ap aging' => '/admin/payables-aging',
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
    'supplier invoices' => '/admin/supplier-invoices',
    'sales orders' => '/admin/sales-orders',
    'quotations' => '/admin/quotations',
    'sales register' => '/admin/sales-register',
    'purchase register' => '/admin/purchase-register',
    'sales returns' => '/admin/sales-returns',
    'mrp' => '/admin/mrp',
    'users' => '/admin/users',
    'tax rates' => '/admin/tax-rates',
    'opening balances' => '/admin/opening-balances',
    'report settings' => '/admin/report-settings',
    'document library' => '/admin/documents',
]);

it('renders admin page for an authorised user', function (string $path) {
    $company = Company::factory()->create();
    $user = superAdminFor($company);

    $this->actingAs($user)->get($path)->assertOk();
})->with('adminPages');
