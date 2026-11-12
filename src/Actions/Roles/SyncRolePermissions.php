<?php

namespace Slakbal\TenancyFoundation\Actions\Roles;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Events\Admin\RolePermissionsChanged;
use Slakbal\TenancyFoundation\Exceptions\InvalidRolePermissions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

readonly class SyncRolePermissions
{
    /**
     * Replace the permissions of a role with exactly the given ones.
     *
     * @param  array<int, BackedEnum|string>  $permissions
     */
    public function handle(Role $role, array $permissions, ?Model $actor = null): void
    {
        $this->guardMutable($role);

        $requested = collect($permissions)
            ->map(fn (BackedEnum|string $permission): string => $permission instanceof BackedEnum ? (string) $permission->value : $permission)
            ->unique()
            ->values();

        $changes = DB::transaction(fn (): ?array => $this->sync($role, $requested->all()));

        if ($changes === null) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        RolePermissionsChanged::dispatch(
            $role->getKey(),
            $role->name,
            $role->guard_name,
            $changes['added'],
            $changes['removed'],
            $actor,
        );
    }

    /**
     * @param  array<int, string>  $requested
     * @return array{added: array<int, string>, removed: array<int, string>}|null
     */
    protected function sync(Role $role, array $requested): ?array
    {
        $permissionClass = app(PermissionRegistrar::class)->getPermissionClass();

        $known = $permissionClass::query()
            ->where('guard_name', $role->guard_name)
            ->whereIn('name', $requested)
            ->pluck('id', 'name');

        $unknown = array_values(array_diff($requested, $known->keys()->all()));

        if ($unknown !== []) {
            throw InvalidRolePermissions::unknownPermissions($unknown, $role->guard_name);
        }

        $current = $role->permissions()->pluck('name')->all();
        $added = array_values(array_diff($requested, $current));
        $removed = array_values(array_diff($current, $requested));

        if ($added === [] && $removed === []) {
            return null;
        }

        $role->permissions()->sync($known->values()->all());

        sort($added);
        sort($removed);

        return ['added' => $added, 'removed' => $removed];
    }

    protected function guardMutable(Role $role): void
    {
        $immutable = match ($role->guard_name) {
            AuthGuard::ADMIN->value => AdminRoles::tryFrom($role->name)?->isLocked() ?? false,
            AuthGuard::TENANT->value => TenantRoles::tryFrom($role->name)?->isLocked() ?? false,
            default => false,
        };

        if ($immutable) {
            throw InvalidRolePermissions::immutableRole($role->name);
        }
    }
}
