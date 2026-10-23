<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

test('UserPolicy (tenant) bulk restoreAny/forceDeleteAny resolve via the shared base exactly as the old explicit methods did', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $tenant = Tenant::factory()->create();
    setPermissionsTeamId($tenant->getKey());

    $without = User::factory()->create();
    $tenant->members()->attach($without, ['is_owner' => false, 'is_default_tenant' => true]);

    $with = User::factory()->create();
    $tenant->members()->attach($with, ['is_owner' => false, 'is_default_tenant' => true]);
    Permission::findOrCreate(TenantPermissions::USER_RESTORE->value, AuthGuard::TENANT->value);
    Permission::findOrCreate(TenantPermissions::USER_FORCE_DELETE->value, AuthGuard::TENANT->value);
    $with->givePermissionTo([TenantPermissions::USER_RESTORE, TenantPermissions::USER_FORCE_DELETE]);

    expect($without->can('restoreAny', User::class))->toBeFalse();
    expect($without->can('forceDeleteAny', User::class))->toBeFalse();

    expect($with->can('restoreAny', User::class))->toBeTrue();
    expect($with->can('forceDeleteAny', User::class))->toBeTrue();
});

test('AdminPolicy and Admin UserPolicy bulk abilities resolve correctly via the shared base', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(1);

    $without = Admin::factory()->create();
    $with = Admin::factory()->create();
    Permission::findOrCreate(AdminPermissions::ADMIN_USER_RESTORE->value, AuthGuard::ADMIN->value);
    $with->givePermissionTo(AdminPermissions::ADMIN_USER_RESTORE);

    expect($without->can('restoreAny', Admin::class))->toBeFalse();
    expect($with->can('restoreAny', Admin::class))->toBeTrue();
});

test('an ability with no mapped permission at all resolves false rather than throwing (e.g. TenantPolicy has no restore)', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $tenant = Tenant::factory()->create();
    setPermissionsTeamId($tenant->getKey());
    $owner = User::factory()->create();
    $tenant->members()->attach($owner, ['is_owner' => true, 'is_default_tenant' => true]);

    expect(fn () => $owner->can('restore', $tenant))->not->toThrow(Throwable::class);
    expect($owner->can('restore', $tenant))->toBeFalse();
});
