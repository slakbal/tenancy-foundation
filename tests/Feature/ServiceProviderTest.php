<?php

use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Plugins\AdminPlugin;
use Slakbal\TenancyFoundation\Plugins\TenantPlugin;

test('package config is merged with default models', function () {
    expect(config('tenancy-foundation.models.tenant'))->toBe(Tenant::class)
        ->and(config('tenancy-foundation.models.user'))->toBe(User::class)
        ->and(config('filament-defaults.enabled'))->toBeTrue();
});

test('plugins expose stable ids', function () {
    expect(AdminPlugin::make()->getId())->toBe('tenancy-foundation-admin')
        ->and(TenantPlugin::make()->getId())->toBe('tenancy-foundation-tenant');
});

test('role seeders are autoloadable', function () {
    expect(class_exists(AdminRolePermissionsSeeder::class))->toBeTrue()
        ->and(class_exists(TenantRolePermissionsSeeder::class))->toBeTrue();
});
