<?php

use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

test('a user with no tenants is logged out when visiting a tenant panel route', function () {
    $user = User::factory()->create();

    // Give the user an existing tenant, then detach it, to prove detection
    // is based on current state rather than a cached collection.
    $tenant = Tenant::factory()->create();
    $tenant->members()->attach($user, ['is_owner' => true, 'is_default_tenant' => true]);
    $tenant->members()->detach($user);

    $this->actingAs($user, AuthGuard::TENANT->value);

    expect(auth(AuthGuard::TENANT->value)->check())->toBeTrue();

    $response = $this->get($this->tenantUrl($tenant, '/'));

    // The middleware logs the user out of the guard before the request
    // continues, so any subsequent check on this guard should be false.
    expect(auth(AuthGuard::TENANT->value)->check())->toBeFalse();
});

test('a user with a tenant is not logged out when visiting a tenant panel route', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->members()->attach($user, ['is_owner' => true, 'is_default_tenant' => true]);

    $this->actingAsTenantUser($user, $tenant);

    $this->get($this->tenantUrl($tenant, '/'));

    expect(auth(AuthGuard::TENANT->value)->check())->toBeTrue();
});
