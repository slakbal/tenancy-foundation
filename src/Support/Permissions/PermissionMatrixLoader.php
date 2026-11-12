<?php

namespace Slakbal\TenancyFoundation\Support\Permissions;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Spatie\Permission\PermissionRegistrar;

class PermissionMatrixLoader
{
    public function load(FilamentPanel $panel): PermissionMatrix
    {
        $guard = $panel->getAuthGuard();
        $names = $this->enumNames($panel);

        $permissions = $this->permissionClass()::query()->where('guard_name', $guard)->get();
        $roles = $this->roles($panel);

        return new PermissionMatrix(
            panel: $panel,
            roles: $roles,
            groups: $this->groups($permissions, $names),
            unknown: $permissions->pluck('name')->diff($names)->sort()->values()->all(),
            missing: array_values(array_diff($names, $permissions->pluck('name')->all())),
            held: $roles->mapWithKeys(fn (Model $role): array => [$role->getKey() => $role->permissions->pluck('name')->all()])->all(),
            lockedRoleIds: $roles->filter(fn (Model $role): bool => $this->isLocked($panel, $role))->map->getKey()->values()->all(),
        );
    }

    /**
     * @return array<int, class-string<BackedEnum>>
     */
    protected function permissionEnums(FilamentPanel $panel): array
    {
        return match ($panel) {
            FilamentPanel::ADMIN => [AdminPermissions::class],
            FilamentPanel::TENANT => app(TenantPermissionEnums::class)->classes(),
        };
    }

    /**
     * @return class-string<BackedEnum>
     */
    protected function roleEnum(FilamentPanel $panel): string
    {
        return match ($panel) {
            FilamentPanel::ADMIN => AdminRoles::class,
            FilamentPanel::TENANT => TenantRoles::class,
        };
    }

    /**
     * @return array<int, string>
     */
    protected function enumNames(FilamentPanel $panel): array
    {
        return collect($this->permissionEnums($panel))
            ->flatMap(fn (string $enum): array => $enum::cases())
            ->map(fn (BackedEnum $permission): string => (string) $permission->value)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Model>
     */
    protected function roles(FilamentPanel $panel): Collection
    {
        $order = array_map(fn (BackedEnum $role): string => (string) $role->value, $this->roleEnum($panel)::cases());

        return app(PermissionRegistrar::class)->getRoleClass()::query()
            ->where('guard_name', $panel->getAuthGuard())
            ->with('permissions')
            ->get()
            ->sortBy(fn (Model $role): string => sprintf('%03d-%s', $this->rank($order, $role->name), $role->name))
            ->values();
    }

    /**
     * @param  array<int, string>  $order
     */
    protected function rank(array $order, string $name): int
    {
        $position = array_search($name, $order, true);

        return $position === false ? count($order) : $position;
    }

    protected function isLocked(FilamentPanel $panel, Model $role): bool
    {
        return $this->roleEnum($panel)::tryFrom($role->name)?->isLocked() ?? false;
    }

    /**
     * @param  Collection<int, Model>  $permissions
     * @param  array<int, string>  $names  enum permission names in their declared order
     * @return array<string, array<int, Model>>
     */
    protected function groups(Collection $permissions, array $names): array
    {
        return $permissions
            ->filter(fn (Model $permission): bool => in_array($permission->name, $names, true))
            ->sortBy(fn (Model $permission): int => (int) array_search($permission->name, $names, true))
            ->groupBy(fn (Model $permission): string => PermissionLabels::groupKey($permission->name))
            ->map(fn (Collection $group): array => $group->values()->all())
            ->all();
    }

    /**
     * @return class-string<Model>
     */
    protected function permissionClass(): string
    {
        return app(PermissionRegistrar::class)->getPermissionClass();
    }
}
