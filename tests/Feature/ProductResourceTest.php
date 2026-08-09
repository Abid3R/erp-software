<?php

use App\Models\Company;
use App\Models\User;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('renders the products page for an authorised user', function () {
    $company = Company::factory()->create();
    $user = superAdminFor($company);

    $this->actingAs($user)
        ->get('/admin/products')
        ->assertOk();
});

it('denies the admin panel to a user without any company membership', function () {
    $user = User::factory()->create(); // no company

    $this->actingAs($user)
        ->get('/admin/products')
        ->assertForbidden();
});
