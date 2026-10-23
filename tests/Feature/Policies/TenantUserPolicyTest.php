<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->tenant = Tenant::factory()->create();
    setPermissionsTeamId($this->tenant->getKey());
});

function userPolicyUserWithPermission(Tenant $tenant, TenantPermissions $permission): User
{
    setPermissionsTeamId($tenant->getKey());
    $user = User::factory()->create();
    Permission::findOrCreate($permission->value, AuthGuard::TENANT->value);
    $user->givePermissionTo($permission);

    return $user;
}

test('viewAny requires USER_VIEW_ANY', function () {
    $without = User::factory()->create();
    $with = userPolicyUserWithPermission($this->tenant, TenantPermissions::USER_VIEW_ANY);

    expect($without->can('viewAny', User::class))->toBeFalse();
    expect($with->can('viewAny', User::class))->toBeTrue();
});

test('view requires USER_VIEW', function () {
    $target = User::factory()->create();

    $without = User::factory()->create();
    $with = userPolicyUserWithPermission($this->tenant, TenantPermissions::USER_VIEW);

    expect($without->can('view', $target))->toBeFalse();
    expect($with->can('view', $target))->toBeTrue();
});

test('create requires USER_CREATE', function () {
    $without = User::factory()->create();
    $with = userPolicyUserWithPermission($this->tenant, TenantPermissions::USER_CREATE);

    expect($without->can('create', User::class))->toBeFalse();
    expect($with->can('create', User::class))->toBeTrue();
});

test('update requires USER_UPDATE', function () {
    $target = User::factory()->create();

    $without = User::factory()->create();
    $with = userPolicyUserWithPermission($this->tenant, TenantPermissions::USER_UPDATE);

    expect($without->can('update', $target))->toBeFalse();
    expect($with->can('update', $target))->toBeTrue();
});

test('delete requires USER_DELETE', function () {
    $target = User::factory()->create();

    $without = User::factory()->create();
    $with = userPolicyUserWithPermission($this->tenant, TenantPermissions::USER_DELETE);

    expect($without->can('delete', $target))->toBeFalse();
    expect($with->can('delete', $target))->toBeTrue();
});
