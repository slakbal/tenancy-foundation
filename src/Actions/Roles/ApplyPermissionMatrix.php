<?php

namespace Slakbal\TenancyFoundation\Actions\Roles;

use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Exceptions\InvalidRolePermissions;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionChangeSet;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionMatrix;
use Slakbal\TenancyFoundation\Support\Permissions\RoleChange;

class ApplyPermissionMatrix
{
    public function __construct(private readonly SyncRolePermissions $syncRolePermissions) {}

    /**
     * Sync every role whose checkboxes differ from the database, one role at a time.
     *
     * @param  array<int|string, array<int|string, mixed>>  $state  role id => permission id => checked
     * @return array{applied: array<int, RoleChange>, failed: array<string, string>} failed maps a role name to its error
     */
    public function handle(PermissionMatrix $matrix, array $state, ?Model $actor = null): array
    {
        $result = ['applied' => [], 'failed' => []];

        foreach (PermissionChangeSet::between($matrix, $state) as $change) {
            try {
                $this->syncRolePermissions->handle($change->role, $change->requested, $actor);
                $result['applied'][] = $change;
            } catch (InvalidRolePermissions $exception) {
                $result['failed'][$change->role->name] = $exception->getMessage();
            }
        }

        return $result;
    }
}
