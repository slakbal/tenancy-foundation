<?php

namespace Slakbal\TenancyFoundation\Support\Permissions;

use BackedEnum;
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Exceptions\InvalidPermissionSeeder;

/**
 * The single answer to "which permissions does the tenant guard know about": whatever the
 * configured tenant seeder (the package one, or an app subclass adding enums) declares.
 */
class TenantPermissionEnums
{
    public const CONFIG_KEY = 'tenancy-foundation.seeders.tenant_role_permissions';

    public function seeder(): TenantRolePermissionsSeeder
    {
        $class = config(self::CONFIG_KEY) ?? TenantRolePermissionsSeeder::class;

        if (! is_string($class) || ! is_a($class, TenantRolePermissionsSeeder::class, true)) {
            throw InvalidPermissionSeeder::notASeeder(is_string($class) ? $class : get_debug_type($class), TenantRolePermissionsSeeder::class, self::CONFIG_KEY);
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
