<?php

use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Models\Role;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

test('visiting a tenant route syncs the permissions team id to that tenant', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->members()->attach($user, ['is_owner' => true, 'is_default_tenant' => true]);

    $this->actingAsTenantUser($user, $tenant);

    $this->get($this->tenantUrl($tenant, '/'));

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($tenant->getKey());
});

test('switching tenants drops the cached roles relation so the new tenant role applies', function () {
    $user = User::factory()->create();

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $tenantA->members()->attach($user, ['is_owner' => true, 'is_default_tenant' => true]);
    $tenantB->members()->attach($user, ['is_owner' => false, 'is_default_tenant' => false]);

    Role::findOrCreate(TenantRoles::OWNER->value, AuthGuard::TENANT->value);
    Role::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value);

    setPermissionsTeamId($tenantA->getKey());
    $user->assignRole(TenantRoles::OWNER);

    setPermissionsTeamId($tenantB->getKey());
    $user->assignRole(TenantRoles::VIEW_ONLY);

    // Simulate having the roles relation cached under tenant A, then visit
    // tenant B's panel: the middleware should drop the stale relation so
    // the role check below reflects tenant B, not the cached tenant A data.
    setPermissionsTeamId($tenantA->getKey());
    $user->load('roles');
    expect($user->hasRole(TenantRoles::OWNER))->toBeTrue();

    $this->actingAsTenantUser($user, $tenantB);
    $this->get($this->tenantUrl($tenantB, '/'));

    expect($user->hasRole(TenantRoles::OWNER))->toBeFalse();
    expect($user->hasRole(TenantRoles::VIEW_ONLY))->toBeTrue();
});

test('switching tenants drops the cached permissions relation so direct permissions do not leak across tenants', function () {
    $user = User::factory()->create();

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $tenantA->members()->attach($user, ['is_owner' => true, 'is_default_tenant' => true]);
    $tenantB->members()->attach($user, ['is_owner' => false, 'is_default_tenant' => false]);

    Permission::findOrCreate('direct.permission', AuthGuard::TENANT->value);

    setPermissionsTeamId($tenantA->getKey());
    $user->givePermissionTo('direct.permission');

    $user->load('permissions');
    expect($user->hasDirectPermission('direct.permission'))->toBeTrue();

    $this->actingAsTenantUser($user, $tenantB);
    $this->get($this->tenantUrl($tenantB, '/'));

    expect($user->hasDirectPermission('direct.permission'))->toBeFalse();
});
