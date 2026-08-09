<?php

namespace App\Domain\Accounting;

use App\Enums\JournalStatus;
use App\Models\Customer;
use App\Models\JournalLine;
use App\Models\Supplier;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-party AR/AP balances, derived from party-tagged control-account lines on the
 * posted ledger (spec #32, #65). Not a separately-maintained balance — it always
 * agrees with the general ledger's control account.
 */
final class PartyLedger
{
    /** Outstanding receivable owed by a customer (positive = they owe us). */
    public static function receivable(Customer $customer): BigDecimal
    {
        return self::balance($customer, (string) config('erp.accounts.receivable'), debitNormal: true);
    }

    /** Outstanding payable owed to a supplier (positive = we owe them). */
    public static function payable(Supplier $supplier): BigDecimal
    {
        return self::balance($supplier, (string) config('erp.accounts.payable'), debitNormal: false);
    }

    private static function balance(Model $party, string $accountCode, bool $debitNormal): BigDecimal
    {
        $row = JournalLine::withoutGlobalScopes()
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journals.status', JournalStatus::Posted->value)
            ->where('journal_lines.company_id', $party->getAttribute('company_id'))
            ->where('accounts.code', $accountCode)
            ->where('journal_lines.party_type', $party->getMorphClass())
            ->where('journal_lines.party_id', $party->getKey())
            ->toBase()
            ->selectRaw('COALESCE(SUM(debit), 0) d, COALESCE(SUM(credit), 0) c')
            ->first();

        $debit = BigDecimal::of((string) $row->d);
        $credit = BigDecimal::of((string) $row->c);

        return ($debitNormal ? $debit->minus($credit) : $credit->minus($debit))
            ->toScale(2, RoundingMode::HALF_UP);
    }
}
