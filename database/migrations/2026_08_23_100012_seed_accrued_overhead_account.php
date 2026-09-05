<?php

use App\Models\Account;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;

/**
 * Ensure every existing company has an Accrued Production Overhead control account
 * (code 2400) so production-costing postings (labour, machine, utilities,
 * overhead capitalised into WIP) work. Non-destructive.
 */
return new class extends Migration
{
    public function up(): void
    {
        $code = (string) config('erp.accounts.accrued_overhead', '2400');

        Company::query()->each(function (Company $company) use ($code): void {
            $exists = Account::withoutGlobalScopes()
                ->where('company_id', $company->getKey())
                ->where('code', $code)
                ->exists();

            if (! $exists) {
                Account::withoutGlobalScopes()->create([
                    'company_id' => $company->getKey(),
                    'code' => $code,
                    'name' => 'Accrued Production Overhead',
                    'type' => 'liability',
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
