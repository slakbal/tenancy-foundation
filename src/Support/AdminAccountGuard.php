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
        $messages = static::messages()[$ability] ?? null;

        if ($messages === null) {
            return null;
        }

        $model = Arr::first(Arr::wrap($arguments));
        $adminModel = config('tenancy-foundation.models.admin');

        if (! $model instanceof $adminModel) {
            return null;
        }

        if ($user instanceof Model && $user->is($model)) {
            return Response::deny($messages['self']);
        }

        return static::isLastActiveSuperAdmin($model) ? Response::deny($messages['last']) : null;
    }

    /**
     * @return array<string, array{self: string, last: string}>
     */
    protected static function messages(): array
    {
        $delete = [
            'self' => __('You cannot delete your own account.'),
            'last' => __('The last active super admin cannot be deleted.'),
        ];

        return [
            'delete' => $delete,
            'forceDelete' => $delete,
            'deactivate' => [
                'self' => __('You cannot deactivate your own account.'),
                'last' => __('The last active super admin cannot be deactivated.'),
            ],
            'changeRole' => [
                'self' => __('You cannot change your own role.'),
                'last' => __('The last active super admin cannot be demoted.'),
            ],
        ];
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
