<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use Auditable, BelongsToCompany;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'category_id', 'brand_id', 'unit_id', 'purchase_unit_id',
        'sales_unit_id', 'sku', 'barcode', 'name', 'description', 'cost_price',
        'selling_price', 'tracks_batch', 'tracks_serial', 'is_service', 'reorder_level', 'is_active',
        'is_textile', 'textile_type', 'fabric_type', 'yarn_type', 'gsm', 'width', 'colour',
        'shade', 'style', 'construction', 'is_roll_tracked', 'default_wastage_percent',
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
            'is_service' => 'boolean',
            'is_active' => 'boolean',
            'is_textile' => 'boolean',
            'is_roll_tracked' => 'boolean',
            'default_wastage_percent' => 'decimal:3',
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

    /** Bills of materials that produce this product. @return HasMany<BillOfMaterials, $this> */
    public function billsOfMaterials(): HasMany
    {
        return $this->hasMany(BillOfMaterials::class);
    }

    /** Manufacturing orders producing this product. @return HasMany<ManufacturingOrder, $this> */
    public function manufacturingOrders(): HasMany
    {
        return $this->hasMany(ManufacturingOrder::class);
    }

    /** The product's standard fabric/knitting specification master. @return HasOne<ProductSpecification, $this> */
    public function specification(): HasOne
    {
        return $this->hasOne(ProductSpecification::class);
    }

    /** The product's standard dyeing specification master (for dyed fabrics). @return HasOne<DyeingSpecification, $this> */
    public function dyeingSpecification(): HasOne
    {
        return $this->hasOne(DyeingSpecification::class);
    }
}
