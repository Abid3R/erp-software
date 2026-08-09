<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Records an immutable audit entry on create/update/delete of the model (spec #30).
 * Captures the acting user, IP, changed values (old/new), and context. Sensitive
 * and noise attributes are excluded.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => static::writeAudit($model, 'created', null, static::auditSnapshot($model)));
        static::updated(fn (Model $model) => static::writeAudit($model, 'updated', static::auditOriginal($model), static::auditChanges($model)));
        static::deleted(fn (Model $model) => static::writeAudit($model, 'deleted', static::auditSnapshot($model), null));
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    protected static function writeAudit(Model $model, string $event, ?array $old, ?array $new): void
    {
        // Nothing changed on an update (only excluded fields touched) → skip noise.
        if ($event === 'updated' && empty($new)) {
            return;
        }

        // request() is always bound (a bare Request in console/queue contexts,
        // where ip() is simply null).
        $request = request();

        AuditLog::query()->create([
            'company_id' => static::auditCompanyId($model),
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'url' => $request->fullUrl(),
        ]);
    }

    /** @return array<string, mixed> */
    protected static function auditSnapshot(Model $model): array
    {
        return array_diff_key($model->attributesToArray(), array_flip(static::auditExcluded()));
    }

    /** @return array<string, mixed> */
    protected static function auditChanges(Model $model): array
    {
        return array_diff_key($model->getChanges(), array_flip(static::auditExcluded()));
    }

    /** @return array<string, mixed> */
    protected static function auditOriginal(Model $model): array
    {
        $original = [];
        foreach (array_keys(static::auditChanges($model)) as $key) {
            $original[$key] = $model->getOriginal($key);
        }

        return $original;
    }

    protected static function auditCompanyId(Model $model): ?int
    {
        $companyId = $model->getAttribute('company_id');

        return $companyId !== null ? (int) $companyId : app(CompanyContext::class)->currentId();
    }

    /** @return list<string> */
    protected static function auditExcluded(): array
    {
        return ['updated_at', 'created_at', 'password', 'remember_token'];
    }
}
