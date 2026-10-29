<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Support\AdminAccountGuard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function safeguardAdmin(?AdminRoles $role = null, bool $withAdminUserPermissions = false): Admin
{
    $admin = Admin::factory()->create();

    if ($role) {
        Role::findOrCreate($role->value, AuthGuard::ADMIN->value);
        $admin->assignRole($role);
    }

    if ($withAdminUserPermissions) {
        foreach ([AdminPermissions::ADMIN_USER_UPDATE, AdminPermissions::ADMIN_USER_DELETE, AdminPermissions::ADMIN_USER_RESTORE, AdminPermissions::ADMIN_USER_FORCE_DELETE] as $permission) {
            Permission::findOrCreate($permission->value, AuthGuard::ADMIN->value);
            $admin->givePermissionTo($permission);
        }
    }

    return $admin;
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));
});

it('stops a super admin deleting themselves', function () {
    $super = safeguardAdmin(AdminRoles::SUPER_ADMIN);
    safeguardAdmin(AdminRoles::SUPER_ADMIN);

    expect($super->can('delete', $super))->toBeFalse()
        ->and($super->can('forceDelete', $super))->toBeFalse();
});

it('stops an admin with delete permission deleting themselves', function () {
    $admin = safeguardAdmin(AdminRoles::ADMIN, withAdminUserPermissions: true);

    expect($admin->can('delete', $admin))->toBeFalse();
});

it('lets a super admin delete another admin', function () {
    $super = safeguardAdmin(AdminRoles::SUPER_ADMIN);

    expect($super->can('delete', safeguardAdmin(AdminRoles::ADMIN)))->toBeTrue()
        ->and($super->can('delete', safeguardAdmin(AdminRoles::SUPER_ADMIN)))->toBeTrue();
});

it('protects the last active super admin from deletion', function () {
    $last = safeguardAdmin(AdminRoles::SUPER_ADMIN);
    $inactiveSuper = safeguardAdmin(AdminRoles::SUPER_ADMIN);
    $inactiveSuper->forceFill(['status' => AdminStatus::INACTIVE])->save();
    $deletedSuper = safeguardAdmin(AdminRoles::SUPER_ADMIN);
    $deletedSuper->delete();

    expect(AdminAccountGuard::isLastActiveSuperAdmin($last))->toBeTrue()
        ->and($inactiveSuper->can('delete', $last))->toBeFalse()
        ->and($inactiveSuper->can('forceDelete', $last))->toBeFalse();
});

it('does not treat a super admin as last while another active one exists', function () {
    $first = safeguardAdmin(AdminRoles::SUPER_ADMIN);
    safeguardAdmin(AdminRoles::SUPER_ADMIN);

    expect(AdminAccountGuard::isLastActiveSuperAdmin($first))->toBeFalse();
});

it('keeps admins with permissions away from super admins', function (string $ability) {
    $admin = safeguardAdmin(AdminRoles::ADMIN, withAdminUserPermissions: true);
    $super = safeguardAdmin(AdminRoles::SUPER_ADMIN);
    safeguardAdmin(AdminRoles::SUPER_ADMIN);

    expect($admin->can($ability, $super))->toBeFalse();
})->with(['update', 'delete', 'restore', 'forceDelete']);

it('lets admins with permissions manage other admins', function (string $ability) {
    $admin = safeguardAdmin(AdminRoles::ADMIN, withAdminUserPermissions: true);

    expect($admin->can($ability, safeguardAdmin(AdminRoles::SUPPORT)))->toBeTrue();
})->with(['update', 'delete', 'restore', 'forceDelete']);
