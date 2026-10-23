<?php

namespace Slakbal\TenancyFoundation\Policies\Tenant;

use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Policies\AuthorisationPolicy;

class TenantPolicy extends AuthorisationPolicy
{
    // when there are specific extra logic needed in a policy check, simply add the specific method, otherwise it will fallback to __call in AuthorisationPolicy
    //    public function update(User $user, Tenant $tenant): bool
    //    {
    //        if (! $user->can(TenantPermissions::TENANT_UPDATE)) {
    //            return false;
    //        }
    //
    //        return $tenant->members()->where('users.id', $user->id)->wherePivot('is_owner', true)->exists();
    //    }

    protected function permissionMap(): array
    {
        return [
            'viewAny' => TenantPermissions::TENANT_VIEW_ANY,
            'view' => TenantPermissions::TENANT_VIEW,
            'create' => TenantPermissions::TENANT_CREATE,
            'update' => TenantPermissions::TENANT_UPDATE,
            'delete' => TenantPermissions::TENANT_DELETE,
        ];
    }
}
