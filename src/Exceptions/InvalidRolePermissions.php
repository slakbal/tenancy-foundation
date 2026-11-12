<?php

namespace Slakbal\TenancyFoundation\Exceptions;

use RuntimeException;

class InvalidRolePermissions extends RuntimeException
{
    public static function immutableRole(string $role): self
    {
        return new self(__('The :role role cannot be changed.', ['role' => $role]));
    }

    /**
     * @param  array<int, string>  $names
     */
    public static function unknownPermissions(array $names, string $guard): self
    {
        return new self(__('Unknown permissions for the :guard guard: :names', [
            'guard' => $guard,
            'names' => implode(', ', $names),
        ]));
    }
}
