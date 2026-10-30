<?php

use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Models\Admin;
use Spatie\Permission\Models\Role;

function adminRolePermissions(AdminRoles $role): array
{
    return Role::findByName($role->value, AuthGuard::ADMIN->value)->permissions->pluck('name')->all();
}

beforeEach(fn () => $this->seed(AdminRolePermissionsSeeder::class));

it('lets the admin role work on contact requests', function () {
    expect(adminRolePermissions(AdminRoles::ADMIN))->toContain(
        AdminPermissions::CONTACT_REQUEST_VIEW_ANY->value,
        AdminPermissions::CONTACT_REQUEST_VIEW->value,
        AdminPermissions::CONTACT_REQUEST_UPDATE->value,
        AdminPermissions::CONTACT_REQUEST_RESPOND->value,
        AdminPermissions::CONTACT_REQUEST_DELETE->value,
    );
});

it('lets the support role work on contact requests but not delete them', function () {
    $support = adminRolePermissions(AdminRoles::SUPPORT);

    expect($support)->toContain(
        AdminPermissions::CONTACT_REQUEST_VIEW_ANY->value,
        AdminPermissions::CONTACT_REQUEST_UPDATE->value,
        AdminPermissions::CONTACT_REQUEST_RESPOND->value,
    )->and($support)->not->toContain(AdminPermissions::CONTACT_REQUEST_DELETE->value);
});

it('lets the support role view admins but not change them', function () {
    $support = adminRolePermissions(AdminRoles::SUPPORT);

    expect($support)->toContain(AdminPermissions::ADMIN_USER_VIEW_ANY->value, AdminPermissions::ADMIN_USER_VIEW->value)
        ->and($support)->not->toContain(
            AdminPermissions::ADMIN_USER_CREATE->value,
            AdminPermissions::ADMIN_USER_UPDATE->value,
            AdminPermissions::ADMIN_USER_DELETE->value,
        );
});

it('gives the admin and support roles the right tenant permissions', function () {
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    $tenantPermissions = fn (AdminRoles $role): array => collect(adminRolePermissions($role))
        ->filter(fn (string $name): bool => str_starts_with($name, 'tenant.'))
        ->sort()
        ->values()
        ->all();

    expect($tenantPermissions(AdminRoles::ADMIN))->toBe(['tenant.cancel', 'tenant.reactivate', 'tenant.update', 'tenant.view', 'tenant.view_any'])
        ->and($tenantPermissions(AdminRoles::SUPPORT))->toBe(['tenant.view', 'tenant.view_any']);
});
