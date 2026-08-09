<?php

namespace App\Domain\Accounting;

use App\Exceptions\PostingException;
use App\Models\Account;
use App\Support\CompanyContext;

/**
 * Resolves a posting role (e.g. 'inventory', 'receivable') to the concrete
 * chart-of-accounts row for a company, via the configurable role→code map
 * (config/erp.php). Keeps standard journal entries free of hard-coded ids.
 */
class LedgerAccounts
{
    public function __construct(private CompanyContext $context) {}

    public function get(string $role, ?int $companyId = null): Account
    {
        $companyId ??= $this->context->currentId();

        /** @var array<string, string> $map */
        $map = config('erp.accounts', []);
        $code = $map[$role] ?? throw new PostingException("No account mapped for role '{$role}'.");

        $account = Account::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->first();

        if ($account === null) {
            throw new PostingException("Mapped account {$code} for role '{$role}' does not exist.");
        }

        return $account;
    }

    /** Resolve a role's account, or null if unmapped/missing (for reports/widgets). */
    public function tryGet(string $role, ?int $companyId = null): ?Account
    {
        try {
            return $this->get($role, $companyId);
        } catch (PostingException) {
            return null;
        }
    }
}
