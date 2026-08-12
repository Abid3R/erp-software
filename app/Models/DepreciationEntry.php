<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $amount
 */
class DepreciationEntry extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['company_id', 'fixed_asset_id', 'period', 'amount', 'journal_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<FixedAsset, $this> */
    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }
}
