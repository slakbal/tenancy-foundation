<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Policy resolution relies on Gate::guessPolicyNamesUsing, which is
    // registered inside TenantPanelProvider's bootUsing callback. That
    // callback only runs when the panel actually boots (normally via the
    // SetUpPanel middleware on a real request), so force it here.
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->tenant = Tenant::factory()->create();
    setPermissionsTeamId($this->tenant->getKey());
});

function tenantUserWithPermission(Tenant $tenant, TenantPermissions $permission): User
{
    setPermissionsTeamId($tenant->getKey());
    $user = User::factory()->create();
    Permission::findOrCreate($permission->value, AuthGuard::TENANT->value);
    $user->givePermissionTo($permission);

    return $user;
}

test('view requires TENANT_VIEW, not TENANT_VIEW_ANY', function () {
    $withViewAny = tenantUserWithPermission($this->tenant, TenantPermissions::TENANT_VIEW_ANY);
    $withView = tenantUserWithPermission($this->tenant, TenantPermissions::TENANT_VIEW);

    expect($withViewAny->can('view', $this->tenant))->toBeFalse();
    expect($withView->can('view', $this->tenant))->toBeTrue();
});

test('create requires TENANT_CREATE, not TENANT_VIEW_ANY', function () {
    $withViewAny = tenantUserWithPermission($this->tenant, TenantPermissions::TENANT_VIEW_ANY);
    $withCreate = tenantUserWithPermission($this->tenant, TenantPermissions::TENANT_CREATE);

    expect($withViewAny->can('create', Tenant::class))->toBeFalse();
    expect($withCreate->can('create', Tenant::class))->toBeTrue();
});

test('update requires TENANT_UPDATE, not TENANT_VIEW_ANY', function () {
    $withViewAny = tenantUserWithPermission($this->tenant, TenantPermissions::TENANT_VIEW_ANY);
    $withUpdate = tenantUserWithPermission($this->tenant, TenantPermissions::TENANT_UPDATE);

    expect($withViewAny->can('update', $this->tenant))->toBeFalse();
    expect($withUpdate->can('update', $this->tenant))->toBeTrue();
});

test('delete requires TENANT_DELETE, not TENANT_VIEW_ANY', function () {
    $withViewAny = tenantUserWithPermission($this->tenant, TenantPermissions::TENANT_VIEW_ANY);
    $withDelete = tenantUserWithPermission($this->tenant, TenantPermissions::TENANT_DELETE);

    expect($withViewAny->can('delete', $this->tenant))->toBeFalse();
    expect($withDelete->can('delete', $this->tenant))->toBeTrue();
});

test('viewAny requires TENANT_VIEW_ANY', function () {
    $withoutPermission = User::factory()->create();
    $withPermission = tenantUserWithPermission($this->tenant, TenantPermissions::TENANT_VIEW_ANY);

    expect($withoutPermission->can('viewAny', Tenant::class))->toBeFalse();
    expect($withPermission->can('viewAny', Tenant::class))->toBeTrue();
});
