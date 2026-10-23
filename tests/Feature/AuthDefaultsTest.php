<?php

use Slakbal\TenancyFoundation\Http\Responses\LogoutResponse;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Tenant;

test('admin guard, provider and password broker are registered by the package', function () {
    expect(config('auth.guards.admin.provider'))->toBe('admins')
        ->and(config('auth.providers.admins.model'))->toBe(Admin::class)
        ->and(config('auth.passwords.admins.provider'))->toBe('admins');
});

test('model factories resolve the configured models', function () {
    expect(Tenant::factory()->make())->toBeInstanceOf(Tenant::class)
        ->and(Admin::factory()->make())->toBeInstanceOf(Admin::class);
});

test('logout response falls back to the root url without a configured route', function () {
    $response = (new LogoutResponse)->toResponse(request());

    expect($response->getTargetUrl())->toBe(url('/'));
});

test('install command runs and publishes the config', function () {
    $this->artisan('tenancy-foundation:install')->assertSuccessful();
});
