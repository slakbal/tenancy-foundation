<?php

namespace Slakbal\TenancyFoundation\Tests\Support;

use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;

/**
 * Stands in for an application admin seeder that adds its own enum through the documented hook.
 */
class AppAdminRolePermissionsSeeder extends AdminRolePermissionsSeeder
{
    protected function permissionEnums(): array
    {
        return [...parent::permissionEnums(), AppAdminPermissions::class];
    }
}
