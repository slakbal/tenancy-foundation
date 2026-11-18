<?php

namespace Slakbal\TenancyFoundation\Support\Permissions;

use BackedEnum;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Exceptions\InvalidPermissionSeeder;

/**
 * The single answer to "which permissions does the admin guard know about": whatever the
 * configured admin seeder (the package one, or an app subclass adding enums) declares.
 */
class AdminPermissionEnums
{
    public const CONFIG_KEY = 'tenancy-foundation.seeders.admin_role_permissions';

    public function seeder(): AdminRolePermissionsSeeder
    {
        $class = config(self::CONFIG_KEY) ?? AdminRolePermissionsSeeder::class;

        if (! is_string($class) || ! is_a($class, AdminRolePermissionsSeeder::class, true)) {
            throw InvalidPermissionSeeder::notASeeder(is_string($class) ? $class : get_debug_type($class), AdminRolePermissionsSeeder::class, self::CONFIG_KEY);
        }

        return app($class);
    }

    /**
     * @return array<int, class-string<BackedEnum>>
     */
    public function classes(): array
    {
        return $this->seeder()->permissionEnumClasses();
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return $this->seeder()->permissionNames();
    }
}
