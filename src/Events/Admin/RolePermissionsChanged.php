<?php

namespace Slakbal\TenancyFoundation\Events\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class RolePermissionsChanged
{
    use Dispatchable;

    /**
     * @param  array<int, string>  $added
     * @param  array<int, string>  $removed
     */
    public function __construct(
        public int|string $roleId,
        public string $roleName,
        public string $guardName,
        public array $added,
        public array $removed,
        public ?Model $actor = null,
    ) {}
}
