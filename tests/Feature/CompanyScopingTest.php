<?php

use App\Http\Middleware\SetCurrentCompany;
use App\Models\Company;
use App\Models\User;
use App\Support\CompanyContext;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/**
 * Regression: SetCurrentCompany must run on Livewire AJAX updates, not only on
 * full page loads — otherwise CompanyContext is unset during table paging / page
 * actions (e.g. the Reports PDF export) and company scoping is not enforced. It is
 * registered with isPersistent: true, which hands it to Livewire's persistent
 * middleware.
 */
it('registers company scoping as persistent Livewire middleware', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company, ['is_default' => true]);

    // Serving a panel request registers the panel's persistent middleware.
    $this->actingAs($user)->get('/admin')->assertSuccessful();

    $persistent = app(PersistentMiddleware::class)->getPersistentMiddleware();

    expect($persistent)->toContain(SetCurrentCompany::class);
});
