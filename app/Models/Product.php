<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'category_id', 'brand_id', 'unit_id', 'purchase_unit_id',
        'sales_unit_id', 'sku', 'barcode', 'name', 'description', 'cost_price',
        'selling_price', 'tracks_batch', 'tracks_serial', 'reorder_level', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'reorder_level' => 'decimal:4',
            'tracks_batch' => 'boolean',
            'tracks_serial' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Unit, $this> The stock/inventory unit. */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
