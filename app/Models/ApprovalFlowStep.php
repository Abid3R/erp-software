<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $sequence
 * @property string $role
 */
class ApprovalFlowStep extends Model
{
    /** @var list<string> */
    protected $fillable = ['approval_flow_id', 'sequence', 'role', 'name'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sequence' => 'integer'];
    }

    /** @return BelongsTo<ApprovalFlow, $this> */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }
}
