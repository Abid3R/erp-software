<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property ApprovalStatus $status
 * @property int|null $current_step
 */
class ApprovalRequest extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'approval_flow_id', 'approvable_type', 'approvable_id',
        'amount', 'status', 'current_step', 'requested_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => ApprovalStatus::class,
            'current_step' => 'integer',
        ];
    }

    /** @return BelongsTo<ApprovalFlow, $this> */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    /** @return MorphTo<Model, $this> */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<ApprovalAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class);
    }

    /** The flow step awaiting action, or null if none is pending. */
    public function currentStep(): ?ApprovalFlowStep
    {
        if ($this->current_step === null) {
            return null;
        }

        return $this->flow->steps()->where('sequence', $this->current_step)->first();
    }
}
