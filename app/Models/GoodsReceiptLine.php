<?php

namespace App\Models;

use App\Enums\QcStatus;
use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property QcStatus $qc_status
 * @property string $ordered_quantity
 * @property string $received_quantity
 * @property string $accepted_quantity
 * @property string $rejected_quantity
 * @property string $unit_cost
 */
class GoodsReceiptLine extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'goods_receipt_id', 'purchase_order_line_id', 'product_id',
        'ordered_quantity', 'received_quantity', 'accepted_quantity', 'rejected_quantity',
        'unit_cost', 'batch_no', 'expiry_date', 'qc_status', 'remarks',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:4',
            'received_quantity' => 'decimal:4',
            'accepted_quantity' => 'decimal:4',
            'rejected_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'expiry_date' => 'date',
            'qc_status' => QcStatus::class,
        ];
    }

    /** Accepted quantity, defaulting to received minus rejected when not set. */
    public function acceptedOrDefault(): BigDecimal
    {
        $accepted = BigDecimal::of($this->accepted_quantity);
        if ($accepted->isPositive()) {
            return $accepted;
        }

        return BigDecimal::of($this->received_quantity)->minus($this->rejected_quantity)->max(BigDecimal::zero());
    }

    /** @return BelongsTo<GoodsReceipt, $this> */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
