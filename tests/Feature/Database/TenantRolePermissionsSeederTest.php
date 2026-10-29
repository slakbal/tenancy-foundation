<?php

use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Spatie\Permission\Models\Role;

function tenantRolePermissions(TenantRoles $role): array
{
    return Role::findByName($role->value, AuthGuard::TENANT->value)->permissions->pluck('name')->sort()->values()->all();
}

beforeEach(fn () => $this->seed(TenantRolePermissionsSeeder::class));

it('gives the owner role no permission rows because the owner bypasses checks', function () {
    expect(tenantRolePermissions(TenantRoles::OWNER))->toBe([]);
});

it('gives the admin role every permission', function () {
    expect(tenantRolePermissions(TenantRoles::ADMIN))
        ->toBe(collect(TenantPermissions::cases())->pluck('value')->sort()->values()->all());
});

it('clears owner permission rows left behind by an earlier seed', function () {
    Role::findByName(TenantRoles::OWNER->value, AuthGuard::TENANT->value)->givePermissionTo(TenantPermissions::USER_VIEW_ANY);

    $this->seed(TenantRolePermissionsSeeder::class);

    expect(tenantRolePermissions(TenantRoles::OWNER))->toBe([]);
});

it('limits the view only role to viewing users', function () {
    expect(tenantRolePermissions(TenantRoles::VIEW_ONLY))
        ->toBe([TenantPermissions::USER_VIEW->value, TenantPermissions::USER_VIEW_ANY->value]);
});
