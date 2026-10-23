<?php

use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Models\Admin;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    setPermissionsTeamId(1);
});

function adminPolicyAdminWithPermission(AdminPermissions $permission): Admin
{
    $admin = Admin::factory()->create();
    Permission::findOrCreate($permission->value, AuthGuard::ADMIN->value);
    $admin->givePermissionTo($permission);

    return $admin;
}

test('viewAny requires ADMIN_USER_VIEW_ANY', function () {
    $without = Admin::factory()->create();
    $with = adminPolicyAdminWithPermission(AdminPermissions::ADMIN_USER_VIEW_ANY);

    expect($without->can('viewAny', Admin::class))->toBeFalse();
    expect($with->can('viewAny', Admin::class))->toBeTrue();
});

test('view requires ADMIN_USER_VIEW', function () {
    $target = Admin::factory()->create();

    $without = Admin::factory()->create();
    $with = adminPolicyAdminWithPermission(AdminPermissions::ADMIN_USER_VIEW);

    expect($without->can('view', $target))->toBeFalse();
    expect($with->can('view', $target))->toBeTrue();
});

test('create requires ADMIN_USER_CREATE', function () {
    $without = Admin::factory()->create();
    $with = adminPolicyAdminWithPermission(AdminPermissions::ADMIN_USER_CREATE);

    expect($without->can('create', Admin::class))->toBeFalse();
    expect($with->can('create', Admin::class))->toBeTrue();
});

test('delete requires ADMIN_USER_DELETE', function () {
    $target = Admin::factory()->create();

    $without = Admin::factory()->create();
    $with = adminPolicyAdminWithPermission(AdminPermissions::ADMIN_USER_DELETE);

    expect($without->can('delete', $target))->toBeFalse();
    expect($with->can('delete', $target))->toBeTrue();
});
