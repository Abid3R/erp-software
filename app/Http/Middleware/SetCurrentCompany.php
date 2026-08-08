<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes the active company for authenticated requests, strictly from the
 * user's own memberships. A company_id remembered in the session is honoured only
 * if the user still belongs to it; otherwise we fall back to their default
 * membership. The client cannot set the company by any other means (spec #6).
 */
class SetCurrentCompany
{
    public function __construct(private CompanyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user instanceof \App\Models\User) {
            $company = null;

            $sessionId = $request->session()->get('current_company_id');
            if ($sessionId) {
                $company = $user->companies()->whereKey($sessionId)->first();
            }

            $company ??= $user->defaultCompany();

            if ($company !== null) {
                $this->context->set($company);
                $request->session()->put('current_company_id', $company->getKey());
            }
        }

        return $next($request);
    }
}
