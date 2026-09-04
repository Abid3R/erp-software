<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single parent → child batch link: how much of an input batch was consumed to
 * produce an output batch. The atom of batch traceability.
 */
class BatchConsumption extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = ['company_id', 'output_batch_id', 'input_batch_id', 'quantity'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    /** @return BelongsTo<Batch, $this> */
    public function outputBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'output_batch_id');
    }

    /** @return BelongsTo<Batch, $this> */
    public function inputBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'input_batch_id');
    }
}
