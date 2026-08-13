<?php

use App\Models\Account;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;

/**
 * Ensures every existing company has a Work-In-Progress control account (code 1300)
 * so manufacturing postings work on data created before WIP tracking existed.
 * Non-destructive: only inserts the account where it is missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $code = (string) config('erp.accounts.wip', '1300');

        Company::query()->each(function (Company $company) use ($code): void {
            $exists = Account::withoutGlobalScopes()
                ->where('company_id', $company->getKey())
                ->where('code', $code)
                ->exists();

            if (! $exists) {
                Account::withoutGlobalScopes()->create([
                    'company_id' => $company->getKey(),
                    'code' => $code,
                    'name' => 'Work In Progress',
                    'type' => 'asset',
                    'is_postable' => true,
                    'is_active' => true,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Leave the account in place on rollback (it may carry postings).
    }
};
