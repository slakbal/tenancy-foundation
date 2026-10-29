<?php

namespace Slakbal\TenancyFoundation\Support;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;

class AdminAccountGuard
{
    /**
     * @param  array<int, mixed>|mixed  $arguments
     */
    public static function denial(mixed $user, string $ability, mixed $arguments): ?Response
    {
        if (! in_array($ability, ['delete', 'forceDelete'], true)) {
            return null;
        }

        $model = Arr::first(Arr::wrap($arguments));
        $adminModel = config('tenancy-foundation.models.admin');

        if (! $model instanceof $adminModel) {
            return null;
        }

        if ($user instanceof Model && $user->is($model)) {
            return Response::deny(__('You cannot delete your own account.'));
        }

        return static::isLastActiveSuperAdmin($model)
            ? Response::deny(__('The last active super admin cannot be deleted.'))
            : null;
    }

    public static function isLastActiveSuperAdmin(Model $admin): bool
    {
        if (! static::isActiveSuperAdmin($admin)) {
            return false;
        }

        return ! $admin::query()
            ->role(AdminRoles::SUPER_ADMIN->value)
            ->where('status', AdminStatus::ACTIVE)
            ->whereKeyNot($admin->getKey())
            ->exists();
    }

    public static function isLockedFor(mixed $actor, Model $admin): bool
    {
        return ($actor instanceof Model && $actor->is($admin)) || static::isLastActiveSuperAdmin($admin);
    }

    public static function isSuperAdmin(mixed $admin): bool
    {
        return $admin instanceof Model && method_exists($admin, 'hasRole') && $admin->hasRole(AdminRoles::SUPER_ADMIN);
    }

    protected static function isActiveSuperAdmin(Model $admin): bool
    {
        return static::isSuperAdmin($admin) && $admin->getAttribute('status') === AdminStatus::ACTIVE && ! $admin->trashed();
    }
}
