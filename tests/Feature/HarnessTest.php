<?php

use App\Models\User;

use function Pest\Laravel\get;

/**
 * Verifies the two-role test harness itself: RefreshDatabase migrates the
 * dedicated erp_test database (as the owner role) and the app boots against it.
 */
it('migrates the test database and persists a model', function () {
    $user = User::factory()->create();

    expect(User::query()->whereKey($user->getKey())->exists())->toBeTrue();
});

it('serves the Filament admin login page', function () {
    get('/admin/login')->assertOk();
});
