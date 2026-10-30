<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;

readonly class RemoveTenantOwner
{
    public function __construct(private AssignTenantRole $assignTenantRole) {}

    public function handle(Model $tenant, Model $owner, ?Model $actor = null): void
    {
        DB::transaction(function () use ($tenant, $owner, $actor): void {
            $owners = $tenant->members()->wherePivot('is_owner', true)->get()->keyBy(fn (Model $member) => $member->getKey());

            if ($actor && ! $owners->has($actor->getKey())) {
                throw new AuthorizationException('Only an owner of the tenant can remove another owner.');
            }

            if (! $owners->has($owner->getKey())) {
                throw new InvalidArgumentException('The user is not an owner of the tenant.');
            }

            if ($owners->count() === 1) {
                throw new AuthorizationException(__('A tenant needs at least one owner. Make another member an owner first.'));
            }

            $tenant->members()->updateExistingPivot($owner->getKey(), ['is_owner' => false]);

            $this->assignTenantRole->handle($owner, $tenant, TenantRoles::ADMIN, TenantRoles::OWNER);

            collect([$actor, $owner])->filter()->each->unsetRelations();
        });
    }
}
