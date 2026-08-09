<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use Auditable, BelongsToCompany;

    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'name', 'code', 'phone', 'email', 'address', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
