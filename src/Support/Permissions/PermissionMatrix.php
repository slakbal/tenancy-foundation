<?php

namespace Slakbal\TenancyFoundation\Support\Permissions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;

/**
 * Everything the permission page shows for one guard: roles (highest authority first), the editable permissions
 * grouped by resource, and the permissions that are read-only or missing.
 */
final readonly class PermissionMatrix
{
    /**
     * @param  Collection<int, Model>  $roles
     * @param  array<string, array<int, Model>>  $groups  editable permissions by resource group
     * @param  array<int, string>  $unknown  database permissions with no enum case
     * @param  array<int, string>  $missing  enum permissions with no database row
     * @param  array<int|string, array<int, string>>  $held  permission names per role id
     * @param  array<int, int|string>  $lockedRoleIds
     */
    public function __construct(
        public FilamentPanel $panel,
        public Collection $roles,
        public array $groups,
        public array $unknown,
        public array $missing,
        public array $held,
        public array $lockedRoleIds,
    ) {}

    public function guard(): string
    {
        return $this->panel->getAuthGuard();
    }

    public function isLocked(Model $role): bool
    {
        return in_array($role->getKey(), $this->lockedRoleIds, true);
    }

    /**
     * @return Collection<int, Model>
     */
    public function editableRoles(): Collection
    {
        return $this->roles->reject(fn (Model $role): bool => $this->isLocked($role))->values();
    }

    /**
     * @return array<int, string>
     */
    public function heldBy(Model $role): array
    {
        return $this->held[$role->getKey()] ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function editableNames(): array
    {
        return collect($this->groups)->flatten()->map(fn (Model $permission): string => $permission->name)->all();
    }

    /**
     * Checkbox state for the editable roles: role id => permission id => held.
     *
     * @return array<int|string, array<int|string, bool>>
     */
    public function state(): array
    {
        $permissions = collect($this->groups)->flatten();

        return $this->editableRoles()
            ->mapWithKeys(fn (Model $role): array => [
                $role->getKey() => $permissions
                    ->mapWithKeys(fn (Model $permission): array => [$permission->getKey() => in_array($permission->name, $this->heldBy($role), true)])
                    ->all(),
            ])
            ->all();
    }
}
