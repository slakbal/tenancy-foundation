<?php

namespace Slakbal\TenancyFoundation\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;

readonly class TransferTenantOwnership
{
    public function __construct(private AssignTenantRole $assignTenantRole) {}

    public function handle(Model $tenant, Model $newOwner, Model $actor): void
    {
        $this->transfer($tenant, $newOwner, $actor);
    }

    public function handleAsAdministrator(Model $tenant, Model $newOwner): void
    {
        $this->transfer($tenant, $newOwner, null);
    }

    protected function transfer(Model $tenant, Model $newOwner, ?Model $actor): void
    {
        DB::transaction(function () use ($tenant, $newOwner, $actor): void {
            $members = $tenant->members()->withTrashed()->get()->keyBy(fn (Model $member) => $member->getKey());
            $owners = $members->filter(fn (Model $member): bool => (bool) $member->pivot->is_owner);

            if ($actor && ! $owners->has($actor->getKey())) {
                throw new AuthorizationException('Only an owner of the tenant can transfer its ownership.');
            }

            if ($owners->has($newOwner->getKey())) {
                throw new InvalidArgumentException('The user is already the owner of the tenant.');
            }

            if (! $members->has($newOwner->getKey()) || $members[$newOwner->getKey()]->trashed()) {
                throw new InvalidArgumentException('The user is not a member of the tenant.');
            }

            $tenant->members()->newPivotQuery()
                ->where('is_owner', true)
                ->update(['is_owner' => false, 'updated_at' => now()]);

            $tenant->members()->updateExistingPivot($newOwner->getKey(), ['is_owner' => true]);

            $owners->each(fn (Model $previousOwner) => $this->assignTenantRole->handle($previousOwner, $tenant, TenantRoles::ADMIN, TenantRoles::OWNER));
            $this->assignTenantRole->handle($newOwner, $tenant, TenantRoles::OWNER);

            foreach (array_filter([$actor, $newOwner]) as $user) {
                $user->unsetRelation('tenants')->unsetRelation('ownedTenants')->unsetRelation('memberTenants');
            }
        });
    }
}
