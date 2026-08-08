<?php

use App\Models\Company;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Filament\Facades\Filament;

beforeEach(function () {
    app(CompanyContext::class)->forget();
});

afterEach(function () {
    app(CompanyContext::class)->forget();
});

it('scopes company-owned records to the active company', function () {
    $a = Company::factory()->create();
    $b = Company::factory()->create();
    $a->warehouses()->create(['name' => 'A Warehouse', 'code' => 'WA']);
    $b->warehouses()->create(['name' => 'B Warehouse', 'code' => 'WB']);

    $ctx = app(CompanyContext::class);

    $ctx->set($a);
    expect(Warehouse::query()->pluck('code')->all())->toBe(['WA']);

    $ctx->set($b);
    expect(Warehouse::query()->pluck('code')->all())->toBe(['WB']);
});

it('auto-stamps company_id from the active context on create', function () {
    $a = Company::factory()->create();
    app(CompanyContext::class)->set($a);

    $warehouse = Warehouse::create(['name' => 'X', 'code' => 'X1']);

    expect($warehouse->company_id)->toBe($a->getKey());
});

it('does not leak another company\'s record even by primary key (IDOR)', function () {
    $a = Company::factory()->create();
    $b = Company::factory()->create();
    $foreign = $b->warehouses()->create(['name' => 'B', 'code' => 'B1']);

    app(CompanyContext::class)->set($a);

    expect(Warehouse::query()->find($foreign->getKey()))->toBeNull();
});

it('restricts tenant access and default company to the user\'s memberships', function () {
    $a = Company::factory()->create();
    $b = Company::factory()->create();
    $user = User::factory()->create();
    $user->companies()->attach($a, ['is_default' => true]);

    expect($user->canAccessTenant($a))->toBeTrue()
        ->and($user->canAccessTenant($b))->toBeFalse()
        ->and($user->defaultCompany()->is($a))->toBeTrue();
});

it('denies panel access without a company membership', function () {
    $panel = Filament::getPanel('admin');
    $user = User::factory()->create();

    expect($user->canAccessPanel($panel))->toBeFalse();

    $user->companies()->attach(Company::factory()->create());

    expect($user->fresh()->canAccessPanel($panel))->toBeTrue();
});
