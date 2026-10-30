<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Support\AdminAccountGuard;

function safeguardAdmin(?AdminRoles $role = null, bool $withAdminUserPermissions = false): Admin
{
    $permissions = $withAdminUserPermissions
        ? [AdminPermissions::ADMIN_USER_UPDATE, AdminPermissions::ADMIN_USER_DELETE, AdminPermissions::ADMIN_USER_RESTORE, AdminPermissions::ADMIN_USER_FORCE_DELETE]
        : [];

    return adminWithAccess(...array_filter([$role, ...$permissions]));
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));
});

it('stops an admin deleting themselves', function (AdminRoles $role, bool $withAdminUserPermissions) {
    $admin = safeguardAdmin($role, $withAdminUserPermissions);
    safeguardAdmin(AdminRoles::SUPER_ADMIN);

    expect($admin->can('delete', $admin))->toBeFalse()
        ->and($admin->can('forceDelete', $admin))->toBeFalse();
})->with([
    'super admin' => [AdminRoles::SUPER_ADMIN, false],
    'admin with delete permission' => [AdminRoles::ADMIN, true],
]);

it('lets a super admin delete another admin', function () {
    $super = safeguardAdmin(AdminRoles::SUPER_ADMIN);

    expect($super->can('delete', safeguardAdmin(AdminRoles::ADMIN)))->toBeTrue()
        ->and($super->can('delete', safeguardAdmin(AdminRoles::SUPER_ADMIN)))->toBeTrue();
});

it('protects the last active super admin from being deleted, deactivated or demoted', function (string $ability) {
    $last = safeguardAdmin(AdminRoles::SUPER_ADMIN);
    $inactiveSuper = safeguardAdmin(AdminRoles::SUPER_ADMIN);
    $inactiveSuper->forceFill(['status' => AdminStatus::INACTIVE])->save();
    $deletedSuper = safeguardAdmin(AdminRoles::SUPER_ADMIN);
    $deletedSuper->delete();

    expect(AdminAccountGuard::isLastActiveSuperAdmin($last))->toBeTrue()
        ->and($inactiveSuper->can($ability, $last))->toBeFalse();
})->with(['delete', 'forceDelete', 'deactivate', 'changeRole']);

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
})->with(['update', 'delete', 'restore', 'forceDelete', 'deactivate', 'changeRole', 'sendPasswordResetLink']);

it('lets admins with permissions manage other admins', function (string $ability) {
    $admin = safeguardAdmin(AdminRoles::ADMIN, withAdminUserPermissions: true);

    expect($admin->can($ability, safeguardAdmin(AdminRoles::SUPPORT)))->toBeTrue();
})->with(['update', 'delete', 'restore']);

it('reserves force delete for super admins', function () {
    $admin = safeguardAdmin(AdminRoles::ADMIN, withAdminUserPermissions: true);
    $super = safeguardAdmin(AdminRoles::SUPER_ADMIN);
    $target = safeguardAdmin(AdminRoles::SUPPORT);

    expect($admin->can('forceDelete', $target))->toBeFalse()
        ->and($admin->can('forceDeleteAny', Admin::class))->toBeFalse()
        ->and($super->can('forceDelete', $target))->toBeTrue();
});
