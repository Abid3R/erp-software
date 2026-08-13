<?php

namespace App\Models;

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property PaymentDirection $direction
 * @property PaymentMethod $method
 */
class Payment extends Model
{
    use Auditable, BelongsToCompany, HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'direction', 'party_type', 'party_id', 'date', 'amount',
        'method', 'reference', 'idempotency_key', 'journal_id', 'note', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'direction' => PaymentDirection::class,
            'method' => PaymentMethod::class,
            'date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function party(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Journal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
