<?php

namespace App\Models;

use App\Enums\RequisitionStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property RequisitionStatus $status
 */
class PurchaseRequisition extends Model
{
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'number', 'department_id', 'needed_by', 'status', 'notes', 'requested_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['needed_by' => 'date', 'status' => RequisitionStatus::class];
    }

    /** @return HasMany<PurchaseRequisitionLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionLine::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
