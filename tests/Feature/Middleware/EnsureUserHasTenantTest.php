<?php

use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

test('a user with no tenants is logged out when visiting a tenant panel route', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs($user, AuthGuard::TENANT->value);

    $this->get($this->tenantUrl($tenant, '/'));

    expect(auth(AuthGuard::TENANT->value)->check())->toBeFalse();
});
