<?php

use App\Models\Account;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;

/**
 * Ensures every existing company has Salary Expense (5400) and Employee Payable
 * (2300) accounts so payroll posting works on companies created before Phase 17.
 * Non-destructive: only inserts each account where it is missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $accounts = [
            ['code' => (string) config('erp.accounts.salary_expense', '5400'), 'name' => 'Salary Expense', 'type' => 'expense'],
            ['code' => (string) config('erp.accounts.employee_payable', '2300'), 'name' => 'Employee Payable (Salaries)', 'type' => 'liability'],
        ];

        Company::query()->each(function (Company $company) use ($accounts): void {
            foreach ($accounts as $acct) {
                $exists = Account::withoutGlobalScopes()
                    ->where('company_id', $company->getKey())
                    ->where('code', $acct['code'])
                    ->exists();
                if ($exists) {
                    continue;
                }
                Account::withoutGlobalScopes()->create([
                    'company_id' => $company->getKey(),
                    'code' => $acct['code'],
                    'name' => $acct['name'],
                    'type' => $acct['type'],
                    'is_postable' => true,
                    'is_active' => true,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Leave accounts in place on rollback — they may carry postings.
    }
};
