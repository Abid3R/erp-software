<?php

namespace App\Models;

use App\Enums\LabDipStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lab dip (colour-development request). Carries no inventory or accounting
 * effect — it is a colour-approval workflow. Once approved it can be attached to
 * a dyeing process order.
 *
 * @property LabDipStatus $status
 */
class LabDip extends Model
{
    use Auditable;
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'reference', 'customer_id', 'colour', 'colour_ref',
        'recipe', 'sample_ref', 'request_date', 'status', 'remarks', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => LabDipStatus::class,
            'request_date' => 'date',
        ];
    }

    public static function booted(): void
    {
        static::creating(function (LabDip $labDip): void {
            if (empty($labDip->reference)) {
                $labDip->reference = DocumentNumber::next('lab_dip', 'LD-', static::query()->count());
            }
        });
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Lab dips approved (internally or by the customer) — usable on a dyeing order. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereIn('status', [
            LabDipStatus::InternalApproved->value,
            LabDipStatus::CustomerApproved->value,
        ]);
    }

    /** A short label for selects: "LD-0001 — Navy Blue". */
    public function label(): string
    {
        return $this->reference.' — '.$this->colour;
    }
}
