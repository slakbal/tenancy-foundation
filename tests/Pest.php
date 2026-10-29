<?php

use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Tests\TestCase;
use Spatie\Permission\Models\Permission;

pest()->extend(TestCase::class)->in('Feature');

function adminWithPermission(AdminPermissions $permission): Admin
{
    $admin = Admin::factory()->create();
    Permission::findOrCreate($permission->value, AuthGuard::ADMIN->value);
    $admin->givePermissionTo($permission);

    return $admin;
}

function tenantUserWithPermission(Tenant $tenant, TenantPermissions $permission): User
{
    setPermissionsTeamId($tenant->getKey());
    $user = User::factory()->create();
    Permission::findOrCreate($permission->value, AuthGuard::TENANT->value);
    $user->givePermissionTo($permission);

    return $user;
}
