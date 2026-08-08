<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;

/**
 * Holds the active company for the current request/console context. This is the
 * single server-side source of the "current company" — resolved from the
 * authenticated user's memberships (never from client input, spec #6). The global
 * CompanyScope and the BelongsToCompany trait read from here.
 */
class CompanyContext
{
    private ?int $companyId = null;

    private ?Company $company = null;

    public function has(): bool
    {
        return $this->companyId !== null;
    }

    public function currentId(): ?int
    {
        return $this->companyId;
    }

    public function current(): ?Company
    {
        if ($this->company === null && $this->companyId !== null) {
            // Company is not itself company-scoped; this is a plain lookup.
            $this->company = Company::query()->find($this->companyId);
        }

        return $this->company;
    }

    public function set(Company|int $company): void
    {
        if ($company instanceof Company) {
            $this->company = $company;
            $this->companyId = $company->getKey();
        } else {
            $this->companyId = $company;
            $this->company = null;
        }
    }

    public function forget(): void
    {
        $this->companyId = null;
        $this->company = null;
    }

    /**
     * Run a callback with a specific company as the active context, restoring the
     * previous context afterwards. Useful for jobs, seeding, and cross-company
     * admin operations.
     *
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    public function runFor(Company|int $company, callable $callback): mixed
    {
        $previousId = $this->companyId;
        $previousCompany = $this->company;

        $this->set($company);

        try {
            return $callback();
        } finally {
            $this->companyId = $previousId;
            $this->company = $previousCompany;
        }
    }

    /** @return class-string */
    public function scopeClass(): string
    {
        return CompanyScope::class;
    }
}
