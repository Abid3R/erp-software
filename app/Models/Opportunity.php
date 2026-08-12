<?php

namespace App\Models;

use App\Enums\OpportunityStage;
use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property OpportunityStage $stage
 * @property string $expected_value
 * @property int $probability
 */
class Opportunity extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'name', 'customer_id', 'lead_id', 'stage', 'expected_value',
        'probability', 'expected_close_date', 'owner_id', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'stage' => OpportunityStage::class,
            'expected_value' => 'decimal:2',
            'probability' => 'integer',
            'expected_close_date' => 'date',
        ];
    }

    /** Value weighted by win probability (expected_value × probability%). */
    public function weightedValue(): BigDecimal
    {
        return BigDecimal::of($this->expected_value)
            ->multipliedBy($this->probability)
            ->dividedBy(100, 2, RoundingMode::HALF_UP);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
