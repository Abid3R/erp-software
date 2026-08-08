<?php

namespace App\Domain\Accounting;

use App\Models\Account;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * A pending, in-memory journal assembled by callers (purchase/sale/payment
 * actions) before posting. Each debit()/credit() adds a one-sided line, so the
 * "exactly one side" invariant holds by construction. PostJournal validates and
 * persists it (spec #9, #12).
 *
 * @phpstan-type DraftLine array{account_id: int, debit: BigDecimal, credit: BigDecimal, memo: ?string}
 */
class JournalDraft
{
    /** @var list<DraftLine> */
    public array $lines = [];

    public function __construct(
        public string $date,
        public ?string $memo = null,
        public ?string $reference = null,
        public ?Model $source = null,
    ) {}

    public static function make(string $date, ?string $memo = null, ?string $reference = null, ?Model $source = null): self
    {
        return new self($date, $memo, $reference, $source);
    }

    public function debit(Account|int $account, BigDecimal|string|int $amount, ?string $memo = null): static
    {
        return $this->line($account, $amount, BigDecimal::zero(), $memo);
    }

    public function credit(Account|int $account, BigDecimal|string|int $amount, ?string $memo = null): static
    {
        return $this->line($account, BigDecimal::zero(), $amount, $memo);
    }

    private function line(Account|int $account, BigDecimal|string|int $debit, BigDecimal|string|int $credit, ?string $memo): static
    {
        $debit = BigDecimal::of($debit);
        $credit = BigDecimal::of($credit);

        if ($debit->isNegative() || $credit->isNegative()) {
            throw new InvalidArgumentException('Journal amounts cannot be negative.');
        }
        if ($debit->isPositive() === $credit->isPositive()) {
            throw new InvalidArgumentException('A journal line must have exactly one of debit or credit.');
        }

        $this->lines[] = [
            'account_id' => $account instanceof Account ? $account->getKey() : $account,
            'debit' => $debit,
            'credit' => $credit,
            'memo' => $memo,
        ];

        return $this;
    }

    public function totalDebit(): BigDecimal
    {
        return array_reduce($this->lines, fn (BigDecimal $c, array $l) => $c->plus($l['debit']), BigDecimal::zero());
    }

    public function totalCredit(): BigDecimal
    {
        return array_reduce($this->lines, fn (BigDecimal $c, array $l) => $c->plus($l['credit']), BigDecimal::zero());
    }

    public function isBalanced(): bool
    {
        return $this->totalDebit()->isEqualTo($this->totalCredit());
    }

    /** @return list<int> */
    public function accountIds(): array
    {
        return array_values(array_unique(array_map(fn (array $l) => $l['account_id'], $this->lines)));
    }
}
