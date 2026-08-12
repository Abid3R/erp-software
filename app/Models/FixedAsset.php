<?php

namespace App\Models;

use App\Enums\FixedAssetStatus;
use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property FixedAssetStatus $status
 * @property string $acquisition_cost
 * @property string $salvage_value
 * @property string $accumulated_depreciation
 * @property int $useful_life_months
 */
class FixedAsset extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'asset_code', 'name', 'category', 'acquisition_date',
        'acquisition_cost', 'salvage_value', 'useful_life_months',
        'accumulated_depreciation', 'status', 'notes',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'salvage_value' => '0',
        'accumulated_depreciation' => '0',
        'status' => 'active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'salvage_value' => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'useful_life_months' => 'integer',
            'status' => FixedAssetStatus::class,
        ];
    }

    /** Cost that is depreciated over the life (cost − salvage). */
    public function depreciableBase(): BigDecimal
    {
        return BigDecimal::of($this->acquisition_cost)->minus($this->salvage_value);
    }

    /** Straight-line charge per month. */
    public function monthlyDepreciation(): BigDecimal
    {
        if ($this->useful_life_months <= 0) {
            return BigDecimal::zero();
        }

        return $this->depreciableBase()->dividedBy($this->useful_life_months, 2, RoundingMode::HALF_UP);
    }

    /** Cost − accumulated depreciation. */
    public function netBookValue(): BigDecimal
    {
        return BigDecimal::of($this->acquisition_cost)->minus($this->accumulated_depreciation);
    }

    /** Depreciation still to be charged. */
    public function remainingDepreciation(): BigDecimal
    {
        return $this->depreciableBase()->minus($this->accumulated_depreciation);
    }

    /** @return HasMany<DepreciationEntry, $this> */
    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(DepreciationEntry::class);
    }
}
