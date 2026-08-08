<?php

namespace App\Models\Scopes;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a company-scoped model to the active company. When no
 * company context is set (console, seeding, pre-auth), the scope is inert — the
 * security boundary is authenticated web requests, where SetCurrentCompany always
 * establishes context. The client can never widen this: company_id is taken from
 * CompanyContext, not from the request (spec #6).
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(CompanyContext::class);

        if ($context->has()) {
            $builder->where($model->getTable().'.company_id', $context->currentId());
        }
    }
}
