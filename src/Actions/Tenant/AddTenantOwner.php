<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;

readonly class AddTenantOwner
{
    public function __construct(private AssignTenantRole $assignTenantRole) {}

    public function handle(Model $tenant, Model $user, ?Model $actor = null): void
    {
        DB::transaction(function () use ($tenant, $user, $actor): void {
            $members = $tenant->members()->get()->keyBy(fn (Model $member) => $member->getKey());

            if ($actor && ! $members->get($actor->getKey())?->pivot->is_owner) {
                throw new AuthorizationException('Only an owner of the tenant can add another owner.');
            }

            if (! $members->has($user->getKey())) {
                throw new InvalidArgumentException('The user is not a member of the tenant.');
            }

            if ($members[$user->getKey()]->pivot->is_owner) {
                throw new InvalidArgumentException('The user is already an owner of the tenant.');
            }

            $tenant->members()->updateExistingPivot($user->getKey(), ['is_owner' => true]);

            $this->assignTenantRole->handle($user, $tenant, TenantRoles::OWNER);

            collect([$actor, $user])->filter()->each->unsetRelations();
        });
    }
}
