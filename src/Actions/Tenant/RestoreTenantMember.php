<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\MemberStatus;
use Slakbal\TenancyFoundation\Support\UserTenantRoles;

readonly class RestoreTenantMember
{
    public function __construct(private AssignTenantRole $assignTenantRole) {}

    /**
     * Restores a soft deleted user and makes sure they are an active member of the tenant again. A user who lost every
     * role when they were removed gets the given role; roles that are still there are kept.
     */
    public function handle(Model $tenant, Model $user, TenantRoles $role = TenantRoles::VIEW_ONLY): Model
    {
        return DB::transaction(function () use ($tenant, $user, $role): Model {
            if ($user->trashed()) {
                $user->restore();
            }

            $membership = $user->tenants()->get()->firstWhere('id', $tenant->getKey());

            if ($membership) {
                $user->tenants()->updateExistingPivot($tenant->getKey(), ['status' => MemberStatus::ACTIVE]);
            } else {
                $tenant->members()->attach($user->getKey(), [
                    'is_owner' => false,
                    'is_default_tenant' => ! $user->tenants()->exists(),
                    'status' => MemberStatus::ACTIVE,
                ]);
            }

            if ((new UserTenantRoles($user))->for($tenant)->isEmpty()) {
                $this->assignTenantRole->handle($user, $tenant, $role);
            }

            $user->unsetRelations();

            return $user;
        });
    }
}
