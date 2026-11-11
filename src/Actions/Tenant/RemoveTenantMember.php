<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Slakbal\TenancyFoundation\Actions\Roles\SyncTenantRoles;
use Slakbal\TenancyFoundation\Events\Tenant\TenantMemberRemoved;

readonly class RemoveTenantMember
{
    public function __construct(private SyncTenantRoles $syncTenantRoles) {}

    /**
     * Removes a user from a tenant. A user with other tenants is detached; a user whose only tenant this is is soft
     * deleted (the membership row stays, so restoring the user brings them back). Returns true when the account was deleted.
     */
    public function handle(Model $tenant, Model $user, ?Model $actor = null): bool
    {
        return DB::transaction(function () use ($tenant, $user, $actor): bool {
            $tenants = $user->tenants()->get();
            $membership = $tenants->firstWhere('id', $tenant->getKey());

            if (! $membership) {
                throw new InvalidArgumentException('The user is not a member of the tenant.');
            }

            if ($membership->pivot->is_owner) {
                throw new AuthorizationException(__('An owner cannot be removed from the tenant. Remove the ownership first.'));
            }

            $remaining = $tenants->reject(fn (Model $other): bool => $other->is($tenant));
            $accountDeleted = $remaining->isEmpty();

            $this->syncTenantRoles->handle($user, $tenant, []);

            if ($accountDeleted) {
                $user->delete();
            } else {
                $user->tenants()->detach($tenant->getKey());

                if ($membership->pivot->is_default_tenant) {
                    $user->tenants()->updateExistingPivot($remaining->first()->getKey(), ['is_default_tenant' => true]);
                }
            }

            $user->unsetRelations();

            DB::afterCommit(fn () => TenantMemberRemoved::dispatch($tenant, $user, $actor, $accountDeleted));

            return $accountDeleted;
        });
    }
}
