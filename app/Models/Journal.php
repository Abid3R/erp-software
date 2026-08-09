<?php

namespace App\Models;

use App\Enums\JournalStatus;
use App\Exceptions\PostingException;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Journal header. Posted journals are immutable — the guards below block any
 * update or delete once posted; corrections happen via reversal (spec #10). A
 * matching PostgreSQL trigger is added in the security-hardening phase as
 * defense in depth (see ACCOUNTING.md).
 *
 * @property JournalStatus $status
 */
class Journal extends Model
{
    use Auditable, BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'accounting_period_id', 'date', 'reference', 'memo',
        'status', 'source_type', 'source_id', 'posted_at', 'posted_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => JournalStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Journal $journal): void {
            if ($journal->getRawOriginal('status') === JournalStatus::Posted->value) {
                throw new PostingException('A posted journal is immutable; correct it with a reversal entry.');
            }
        });

        static::deleting(function (Journal $journal): void {
            if ($journal->getRawOriginal('status') === JournalStatus::Posted->value) {
                throw new PostingException('A posted journal cannot be deleted; correct it with a reversal entry.');
            }
        });
    }

    /** @return BelongsTo<AccountingPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    /** @return HasMany<JournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function totalDebit(): BigDecimal
    {
        return $this->lines->reduce(
            fn (BigDecimal $carry, JournalLine $line) => $carry->plus($line->debit),
            BigDecimal::zero(),
        );
    }

    public function totalCredit(): BigDecimal
    {
        return $this->lines->reduce(
            fn (BigDecimal $carry, JournalLine $line) => $carry->plus($line->credit),
            BigDecimal::zero(),
        );
    }

    public function isBalanced(): bool
    {
        return $this->totalDebit()->isEqualTo($this->totalCredit());
    }
}
