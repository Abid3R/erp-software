<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalAction extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['approval_request_id', 'step', 'user_id', 'action', 'comment'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['step' => 'integer', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<ApprovalRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
