<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $unit_price
 */
class SupplierPrice extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = ['company_id', 'supplier_id', 'product_id', 'unit_price', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['unit_price' => 'decimal:4', 'is_active' => 'boolean'];
    }

    /** The active buying price for a supplier/product, or null. */
    public static function priceFor(int $supplierId, int $productId): ?string
    {
        $price = self::query()
            ->where('supplier_id', $supplierId)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->value('unit_price');

        return $price === null ? null : (string) $price;
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
