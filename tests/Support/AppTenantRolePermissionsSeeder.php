<?php

namespace Slakbal\TenancyFoundation\Tests\Support;

use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;

/**
 * Stands in for an application seeder that adds its own enum through the documented hook.
 */
class AppTenantRolePermissionsSeeder extends TenantRolePermissionsSeeder
{
    protected function permissionEnums(): array
    {
        return [...parent::permissionEnums(), AppPermissions::class];
    }
}
