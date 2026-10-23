<?php

namespace Slakbal\TenancyFoundation\Policies\Admin;

use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Policies\AuthorisationPolicy;

class AdminPolicy extends AuthorisationPolicy
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
            'viewAny' => AdminPermissions::ADMIN_USER_VIEW_ANY,
            'view' => AdminPermissions::ADMIN_USER_VIEW,
            'create' => AdminPermissions::ADMIN_USER_CREATE,
            'update' => AdminPermissions::ADMIN_USER_UPDATE,
            'delete' => AdminPermissions::ADMIN_USER_DELETE,
            'deleteAny' => AdminPermissions::ADMIN_USER_DELETE,
            'forceDelete' => AdminPermissions::ADMIN_USER_FORCE_DELETE,
            'forceDeleteAny' => AdminPermissions::ADMIN_USER_FORCE_DELETE,
            'restore' => AdminPermissions::ADMIN_USER_RESTORE,
            'restoreAny' => AdminPermissions::ADMIN_USER_RESTORE,
        ];
    }
}
