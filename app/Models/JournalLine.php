<?php

namespace App\Models;

use App\Enums\JournalStatus;
use App\Exceptions\PostingException;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single debit or credit against one account. Immutable once its journal is
 * posted (spec #10).
 *
 * @property string $debit
 * @property string $credit
 */
class JournalLine extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'journal_id', 'account_id', 'debit', 'credit', 'memo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        $guard = function (JournalLine $line): void {
            if ($line->journal?->status === JournalStatus::Posted) {
                throw new PostingException('Lines of a posted journal are immutable; correct via reversal.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    /** @return BelongsTo<Journal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
