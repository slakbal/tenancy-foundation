<?php

namespace Slakbal\TenancyFoundation\Support\Permissions;

use Illuminate\Database\Eloquent\Model;

/**
 * Compares the checkbox state of the page with what the database holds right now.
 */
class PermissionChangeSet
{
    /**
     * Only the permissions the page can edit are compared; unknown permissions a role holds are always kept.
     *
     * @param  array<int|string, array<int|string, mixed>>  $state  role id => permission id => checked
     * @return array<int, RoleChange>
     */
    public static function between(PermissionMatrix $matrix, array $state): array
    {
        $permissions = collect($matrix->groups)->flatten();
        $editable = $matrix->editableNames();

        return $matrix->editableRoles()
            ->filter(fn (Model $role): bool => isset($state[$role->getKey()]))
            ->map(function (Model $role) use ($matrix, $state, $permissions, $editable): RoleChange {
                $held = $matrix->heldBy($role);
                $checked = $permissions
                    ->filter(fn (Model $permission): bool => (bool) ($state[$role->getKey()][$permission->getKey()] ?? false))
                    ->map(fn (Model $permission): string => $permission->name)
                    ->values()
                    ->all();

                return new RoleChange(
                    panel: $matrix->panel,
                    role: $role,
                    added: array_values(array_diff($checked, $held)),
                    removed: array_values(array_diff(array_intersect($held, $editable), $checked)),
                    requested: [...array_diff($held, $editable), ...$checked],
                );
            })
            ->reject(fn (RoleChange $change): bool => $change->isEmpty())
            ->values()
            ->all();
    }

    /**
     * Number of checkboxes that differ between two states, without touching the database.
     *
     * @param  array<int|string, array<int|string, mixed>>  $original
     * @param  array<int|string, array<int|string, mixed>>  $current
     */
    public static function countDifferences(array $original, array $current): int
    {
        $differences = 0;

        foreach ($original as $roleId => $cells) {
            foreach ($cells as $permissionId => $checked) {
                $differences += (int) ((bool) $checked !== (bool) ($current[$roleId][$permissionId] ?? false));
            }
        }

        return $differences;
    }
}
