<?php

use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    setPermissionsTeamId(1);
});

function adminWithPermission(AdminPermissions $permission): Admin
{
    $admin = Admin::factory()->create();
    Permission::findOrCreate($permission->value, AuthGuard::ADMIN->value);
    $admin->givePermissionTo($permission);

    return $admin;
}

test('viewAny requires USER_VIEW_ANY (tenant users, as seen by admins)', function () {
    $without = Admin::factory()->create();
    $with = adminWithPermission(AdminPermissions::USER_VIEW_ANY);

    expect($without->can('viewAny', User::class))->toBeFalse();
    expect($with->can('viewAny', User::class))->toBeTrue();
});

test('view requires USER_VIEW', function () {
    $target = User::factory()->create();

    $without = Admin::factory()->create();
    $with = adminWithPermission(AdminPermissions::USER_VIEW);

    expect($without->can('view', $target))->toBeFalse();
    expect($with->can('view', $target))->toBeTrue();
});

test('create requires USER_CREATE', function () {
    $without = Admin::factory()->create();
    $with = adminWithPermission(AdminPermissions::USER_CREATE);

    expect($without->can('create', User::class))->toBeFalse();
    expect($with->can('create', User::class))->toBeTrue();
});

test('delete requires USER_DELETE', function () {
    $target = User::factory()->create();

    $without = Admin::factory()->create();
    $with = adminWithPermission(AdminPermissions::USER_DELETE);

    expect($without->can('delete', $target))->toBeFalse();
    expect($with->can('delete', $target))->toBeTrue();
});
