<?php

namespace Slakbal\TenancyFoundation\Policies;

use BackedEnum;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Shared authorization dispatch for the parallel Slakbal\TenancyFoundation\Policies\Admin\* and
 * App\Policies\Tenant\* policies.
 *
 * Each concrete policy declares which abilities it supports via
 * permissionMap(), mapping an ability name (viewAny, view, create, update,
 * delete, deleteAny, restore, restoreAny, forceDelete, forceDeleteAny) to
 * the permission enum case that guards it. Abilities omitted from the map
 * resolve to false, matching Laravel's Gate behaviour for a policy with no
 * method for that ability (e.g. Invitation has no update/restore), so
 * introducing this base class does not change which abilities a panel
 * supports.
 *
 * Every standard ability is declared as a real method (not dispatched via
 * __call()) because Filament panels run with ->strictAuthorization(), which
 * checks method_exists() on the resolved policy before invoking an ability;
 * method_exists() does not see methods only reachable through __call().
 * Parameters are intentionally untyped so a concrete policy can override
 * any single ability with a narrower, typed signature (e.g. to add
 * ownership or self-lockout checks) without violating PHP's rule that an
 * overriding method's parameters must be at least as permissive as the
 * parent's.
 */
abstract class AuthorisationPolicy
{
    /**
     * @return array<string, BackedEnum>
     */
    abstract protected function permissionMap(): array;

    /**
     * @param  mixed  $user
     */
    public function viewAny($user): bool
    {
        return $this->resolve('viewAny', $user);
    }

    /**
     * @param  mixed  $user
     * @param  mixed  $model
     */
    public function view($user, $model): bool
    {
        return $this->resolve('view', $user);
    }

    /**
     * @param  mixed  $user
     */
    public function create($user): bool
    {
        return $this->resolve('create', $user);
    }

    /**
     * @param  mixed  $user
     * @param  mixed  $model
     */
    public function update($user, $model): bool
    {
        return $this->resolve('update', $user);
    }

    /**
     * @param  mixed  $user
     * @param  mixed  $model
     */
    public function delete($user, $model): bool
    {
        return $this->resolve('delete', $user);
    }

    /**
     * @param  mixed  $user
     */
    public function deleteAny($user): bool
    {
        return $this->resolve('deleteAny', $user);
    }

    /**
     * @param  mixed  $user
     * @param  mixed  $model
     */
    public function restore($user, $model): bool
    {
        return $this->resolve('restore', $user);
    }

    /**
     * @param  mixed  $user
     */
    public function restoreAny($user): bool
    {
        return $this->resolve('restoreAny', $user);
    }

    /**
     * @param  mixed  $user
     * @param  mixed  $model
     */
    public function forceDelete($user, $model): bool
    {
        return $this->resolve('forceDelete', $user);
    }

    /**
     * @param  mixed  $user
     */
    public function forceDeleteAny($user): bool
    {
        return $this->resolve('forceDeleteAny', $user);
    }

    /**
     * @param  mixed  $user
     */
    private function resolve(string $ability, $user): bool
    {
        $permission = $this->permissionMap()[$ability] ?? null;

        if (! $permission instanceof BackedEnum) {
            return false;
        }

        /** @var Authorizable $user */
        return $user->can($permission);
    }
}
