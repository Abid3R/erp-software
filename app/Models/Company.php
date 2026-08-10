<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A company / organization — the tenancy boundary. Not itself company-scoped.
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name', 'legal_name', 'code', 'currency_code', 'currency_symbol',
        'timezone', 'fiscal_year_start_month', 'tax_registration_number',
        'default_tax_rate', 'allow_negative_stock', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fiscal_year_start_month' => 'integer',
            'default_tax_rate' => 'decimal:3',
            'allow_negative_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    /** @return HasOne<ReportSetting, $this> */
    public function reportSetting(): HasOne
    {
        return $this->hasOne(ReportSetting::class);
    }

    /** The company's report settings, or a fresh unsaved default. */
    public function reportSettingOrNew(): ReportSetting
    {
        return $this->reportSetting()->first()
            ?? new ReportSetting(['company_id' => $this->getKey()]);
    }
}
