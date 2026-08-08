<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every company-scoped model. Adds the global CompanyScope and
 * auto-stamps company_id from the active context on create, so callers can never
 * forget it and can never spoof it (spec #6).
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function ($model): void {
            if (empty($model->company_id)) {
                $context = app(CompanyContext::class);

                if ($context->has()) {
                    $model->company_id = $context->currentId();
                }
            }
        });
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
