<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

// Feature & end-to-end tests get a freshly-migrated, transaction-wrapped DB.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'EndToEnd');

// Concurrency tests need REAL committed rows across parallel connections, so they
// must NOT be wrapped in a transaction (no RefreshDatabase). See TESTING.md.
pest()->extend(TestCase::class)->in('Concurrency');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A user who is a member of the given company and holds Shield's super_admin role
 * (bypasses all resource policies). Use for panel tests that aren't about RBAC.
 */
function superAdminFor(App\Models\Company $company): App\Models\User
{
    $user = App\Models\User::factory()->create();
    $user->companies()->attach($company, ['is_default' => true]);
    $user->assignRole(Spatie\Permission\Models\Role::findOrCreate('super_admin'));

    return $user;
}

/** A company member with the given roles (created if missing) and no others. */
function memberWithRoles(App\Models\Company $company, string ...$roles): App\Models\User
{
    $user = App\Models\User::factory()->create();
    $user->companies()->attach($company, ['is_default' => true]);

    foreach ($roles as $role) {
        $user->assignRole(Spatie\Permission\Models\Role::findOrCreate($role));
    }

    return $user;
}
