<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property PayrollStatus $status
 * @property int $year
 * @property int $month
 * @property int|null $journal_id
 * @property \Illuminate\Support\Carbon|null $posted_at
 */
class PayrollRun extends Model
{
    use Auditable;
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = ['company_id', 'name', 'year', 'month', 'status', 'journal_id', 'posted_at', 'created_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'status' => PayrollStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    public function periodLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    /** Sum of every payslip's net pay — the amount posted to the GL. */
    public function netTotal(): BigDecimal
    {
        return $this->payslips->reduce(
            fn (BigDecimal $c, Payslip $p): BigDecimal => $c->plus(BigDecimal::of($p->net ?? '0')),
            BigDecimal::zero(),
        );
    }

    /** @return HasMany<Payslip, $this> */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /** @return BelongsTo<Journal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
