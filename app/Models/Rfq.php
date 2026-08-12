<?php

namespace App\Models;

use App\Enums\RfqStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property RfqStatus $status
 */
class Rfq extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'number', 'purchase_requisition_id', 'warehouse_id', 'due_date', 'status', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['due_date' => 'date', 'status' => RfqStatus::class];
    }

    /** @return HasMany<RfqLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(RfqLine::class);
    }

    /** @return HasMany<RfqQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(RfqQuote::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
