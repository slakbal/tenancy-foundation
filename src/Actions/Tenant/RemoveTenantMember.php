<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Slakbal\TenancyFoundation\Actions\Roles\SyncTenantRoles;

readonly class RemoveTenantMember
{
    public function __construct(private SyncTenantRoles $syncTenantRoles) {}

    public function handle(Model $tenant, Model $user): void
    {
        DB::transaction(function () use ($tenant, $user): void {
            $membership = $user->tenants()->get()->firstWhere('id', $tenant->getKey());

            if (! $membership) {
                throw new InvalidArgumentException('The user is not a member of the tenant.');
            }

            if ($membership->pivot->is_owner) {
                throw new AuthorizationException(__('An owner cannot be removed from the tenant. Remove the ownership first.'));
            }

            $remaining = $user->tenants()->get()->reject(fn (Model $other): bool => $other->is($tenant));

            if ($remaining->isEmpty()) {
                throw new AuthorizationException(__('A user cannot be removed from their only tenant.'));
            }

            $this->syncTenantRoles->handle($user, $tenant, []);

            $user->tenants()->detach($tenant->getKey());

            if ($membership->pivot->is_default_tenant) {
                $user->tenants()->updateExistingPivot($remaining->first()->getKey(), ['is_default_tenant' => true]);
            }

            $user->unsetRelations();
        });
    }
}
