<?php

namespace App\Models;

use App\Enums\PeriodStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property PeriodStatus $status
 */
class AccountingPeriod extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'name', 'fiscal_year', 'start_date', 'end_date', 'status', 'closed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => PeriodStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function acceptsPostings(): bool
    {
        return $this->status->acceptsPostings();
    }
}
