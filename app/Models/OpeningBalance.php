<?php

namespace App\Models;

use App\Enums\OpeningBalanceStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property OpeningBalanceStatus $status
 */
class OpeningBalance extends Model
{
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = ['company_id', 'number', 'as_of_date', 'status', 'notes'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['as_of_date' => 'date', 'status' => OpeningBalanceStatus::class];
    }

    /** @return HasMany<OpeningBalanceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(OpeningBalanceLine::class);
    }
}
