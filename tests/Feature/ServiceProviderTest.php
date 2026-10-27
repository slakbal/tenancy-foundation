<?php

use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\AccountCancelled;
use Slakbal\TenancyFoundation\Plugins\AdminPlugin;
use Slakbal\TenancyFoundation\Plugins\TenantPlugin;
use Slakbal\TenancyFoundation\TenancyFoundationServiceProvider;

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

test('config sections published by an older version still receive the new default keys', function () {
    config([
        'tenancy-foundation.notifications' => ['welcome' => 'App\Notifications\CustomWelcome'],
        'tenancy-foundation.cancellation' => ['grace_days' => 7],
    ]);

    (new TenancyFoundationServiceProvider(app()))->register();

    expect(config('tenancy-foundation.notifications.welcome'))->toBe('App\Notifications\CustomWelcome')
        ->and(config('tenancy-foundation.notifications.cancelled'))->toBe(AccountCancelled::class)
        ->and(config('tenancy-foundation.cancellation.grace_days'))->toBe(7)
        ->and(config('tenancy-foundation.cancellation.schedule_deletion'))->toBeTrue()
        ->and(config('tenancy-foundation.cancellation.deletion_time'))->toBe('01:00');
});

test('a section that explicitly disables a notification keeps it disabled', function () {
    config(['tenancy-foundation.notifications' => ['welcome' => null, 'cancelled' => null]]);

    (new TenancyFoundationServiceProvider(app()))->register();

    expect(config('tenancy-foundation.notifications.welcome'))->toBeNull()
        ->and(config('tenancy-foundation.notifications.cancelled'))->toBeNull();
});
