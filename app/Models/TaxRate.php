<?php

namespace App\Models;

use App\Domain\Accounting\LedgerAccounts;
use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A configurable tax/VAT rate (spec: Phase 4). Rates carry an effective window so a
 * change is a new row, never a code edit. Computation is done in BigDecimal and the
 * result rounded to currency precision. See [[erp-laravel-project]] tax config.
 *
 * @property string $rate_percent
 * @property bool $is_inclusive
 * @property int|null $input_account_id
 * @property int|null $output_account_id
 */
class TaxRate extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'code', 'name', 'rate_percent', 'is_inclusive',
        'input_account_id', 'output_account_id', 'effective_from', 'effective_to', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:4',
            'is_inclusive' => 'boolean',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * The rate in force for a code on a given date (active, within its effective
     * window), preferring the most recently effective one. Null if none applies.
     */
    public static function effective(string $code, string $date, ?int $companyId = null): ?self
    {
        return self::query()
            ->when($companyId !== null, fn (Builder $q) => $q->where('company_id', $companyId))
            ->where('code', $code)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->first();
    }

    /** Tax on a tax-exclusive base amount: base × rate%. */
    public function taxOnExclusive(BigDecimal|string|int $base, int $scale = 2): BigDecimal
    {
        return BigDecimal::of($base)
            ->multipliedBy($this->rateFraction())
            ->toScale($scale, RoundingMode::HALF_UP);
    }

    /** Tax embedded in a tax-inclusive gross amount: gross × rate/(100+rate). */
    public function taxInInclusive(BigDecimal|string|int $gross, int $scale = 2): BigDecimal
    {
        $rate = BigDecimal::of($this->rate_percent);
        $denominator = BigDecimal::of(100)->plus($rate);
        if ($denominator->isZero()) {
            return BigDecimal::zero()->toScale($scale);
        }

        return BigDecimal::of($gross)
            ->multipliedBy($rate)
            ->dividedBy($denominator, $scale, RoundingMode::HALF_UP);
    }

    /**
     * Tax amount for a line total, honouring the inclusive/exclusive flag.
     */
    public function taxFor(BigDecimal|string|int $amount, int $scale = 2): BigDecimal
    {
        return $this->is_inclusive
            ? $this->taxInInclusive($amount, $scale)
            : $this->taxOnExclusive($amount, $scale);
    }

    /** Input-VAT (purchases) account — the override, else the config 'input_vat' role. */
    public function inputAccount(LedgerAccounts $accounts): Account
    {
        return $this->input
            ?? $accounts->get('input_vat', (int) $this->company_id);
    }

    /** Output-VAT (sales) account — the override, else the config 'output_vat' role. */
    public function outputAccount(LedgerAccounts $accounts): Account
    {
        return $this->output
            ?? $accounts->get('output_vat', (int) $this->company_id);
    }

    private function rateFraction(): BigDecimal
    {
        return BigDecimal::of($this->rate_percent)->dividedBy(100, 8, RoundingMode::HALF_UP);
    }

    /** @return BelongsTo<Account, $this> */
    public function input(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'input_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function output(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'output_account_id');
    }
}
