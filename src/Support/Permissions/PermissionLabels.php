<?php

namespace Slakbal\TenancyFoundation\Support\Permissions;

use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\PermissionAction;
use Slakbal\TenancyFoundation\Enums\Authorisation\PermissionGroup;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;

/**
 * Turns a `resource.action` permission name into readable copy, falling back to a headline for names the enums do not know.
 */
class PermissionLabels
{
    public static function group(string $name): string
    {
        $group = Str::before($name, '.');

        return PermissionGroup::tryFrom($group)?->getLabel() ?? Str::headline($group);
    }

    public static function role(string $name): string
    {
        return (AdminRoles::tryFrom($name) ?? TenantRoles::tryFrom($name))?->getLabel() ?? Str::headline($name);
    }

    public static function groupKey(string $name): string
    {
        return Str::before($name, '.');
    }

    public static function action(string $name): string
    {
        $action = Str::after($name, '.');

        return PermissionAction::tryFrom($action)?->getLabel() ?? Str::headline($action);
    }

    public static function full(string $name): string
    {
        return static::group($name).': '.static::action($name);
    }

    /**
     * @param  array<int, string>  $names
     */
    public static function fullList(array $names): string
    {
        return collect($names)->map(fn (string $name): string => static::full($name))->implode(', ');
    }
}
