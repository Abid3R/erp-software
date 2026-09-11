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
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
        'company_id', 'reference', 'customer_id', 'sales_order_id', 'proforma_invoice_id',
        'colour', 'colour_ref', 'dyeing_process', 'substrate', 'gsm', 'liquor_ratio', 'temperature',
        'dyeing_time', 'ph', 'shade_percentage', 'fastness_wash', 'fastness_rubbing',
        'fastness_light', 'recipe', 'recipe_version', 'sample_ref', 'request_date', 'status',
        'remarks', 'approved_by', 'approved_at', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => LabDipStatus::class,
            'request_date' => 'date',
            'temperature' => 'decimal:2',
            'dyeing_time' => 'integer',
            'ph' => 'decimal:2',
            'shade_percentage' => 'decimal:3',
            'approved_at' => 'datetime',
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

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** @return BelongsTo<ProformaInvoice, $this> */
    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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

    /** The dyeing recipe (dyes % owf, chemicals g/L) used to calculate an order's requirement. @return MorphMany<RecipeConsumption, $this> */
    public function consumptions(): MorphMany
    {
        return $this->morphMany(RecipeConsumption::class, 'holder')->orderBy('sort');
    }

    /**
     * Litres of dye bath per unit of fabric, parsed from the liquor ratio.
     * "1:8" → 8.0 (8 L water per kg goods); a plain number is used as-is.
     */
    public function liquorFactor(): ?float
    {
        return self::parseLiquorFactor($this->liquor_ratio);
    }

    public static function parseLiquorFactor(?string $ratio): ?float
    {
        $ratio = trim((string) $ratio);
        if ($ratio === '') {
            return null;
        }
        if (str_contains($ratio, ':')) {
            [$goods, $water] = array_pad(explode(':', $ratio, 2), 2, null);
            $goods = (float) $goods;
            $water = (float) $water;

            return $goods > 0 ? $water / $goods : ($water ?: null);
        }

        return is_numeric($ratio) ? (float) $ratio : null;
    }
}
