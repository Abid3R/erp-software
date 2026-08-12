<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $unit_price
 */
class RfqQuote extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = ['company_id', 'rfq_id', 'rfq_line_id', 'supplier_id', 'unit_price'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['unit_price' => 'decimal:4'];
    }

    /** @return BelongsTo<RfqLine, $this> */
    public function rfqLine(): BelongsTo
    {
        return $this->belongsTo(RfqLine::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
