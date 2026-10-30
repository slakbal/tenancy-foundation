<?php

use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Tests\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

pest()->extend(TestCase::class)->in('Feature');

function adminWithAccess(AdminRoles|AdminPermissions ...$grants): Admin
{
    $admin = Admin::factory()->create();

    foreach ($grants as $grant) {
        if ($grant instanceof AdminRoles) {
            Role::findOrCreate($grant->value, AuthGuard::ADMIN->value);
            $admin->assignRole($grant);

            continue;
        }

        Permission::findOrCreate($grant->value, AuthGuard::ADMIN->value);
        $admin->givePermissionTo($grant);
    }

    return $admin;
}

function tenantMemberWithPermission(Tenant $tenant, TenantPermissions ...$permissions): User
{
    $user = User::factory()->create();
    $tenant->members()->attach($user->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission->value, AuthGuard::TENANT->value);
        $user->givePermissionTo($permission);
    }

    return $user;
}
