<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackingListLine extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'packing_list_id', 'product_id', 'package_no', 'carton_no',
        'quantity', 'net_weight', 'gross_weight', 'dimensions', 'marks_numbers',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'net_weight' => 'decimal:4',
            'gross_weight' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<PackingList, $this> */
    public function packingList(): BelongsTo
    {
        return $this->belongsTo(PackingList::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
