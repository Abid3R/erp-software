<?php

use App\Models\Company;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/**
 * Shield RBAC (spec #5): resource access is governed by role permissions. A user
 * with no granting role is denied; super_admin bypasses via the Shield gate.
 */
it('denies a user with no role access to a governed resource', function () {
    $company = Company::factory()->create();
    $user = memberWithRoles($company); // member, but no roles/permissions

    $this->actingAs($user)->get('/admin/products')->assertForbidden();
});

it('denies a roleless user the user-management screen', function () {
    $company = Company::factory()->create();
    $user = memberWithRoles($company);

    $this->actingAs($user)->get('/admin/users')->assertForbidden();
});

it('grants super_admin access to resources and user management', function () {
    $company = Company::factory()->create();
    $user = superAdminFor($company);

    $this->actingAs($user)->get('/admin/products')->assertOk();
    $this->actingAs($user)->get('/admin/users')->assertOk();
});
