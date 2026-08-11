<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = ['company_id', 'name', 'date', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'date', 'is_active' => 'boolean'];
    }
}
