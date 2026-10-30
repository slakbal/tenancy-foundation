<?php

namespace Slakbal\TenancyFoundation\Policies\Tenant;

use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Policies\AuthorisationPolicy;

class UserPolicy extends AuthorisationPolicy
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

    public function removeMember($user, $model): bool
    {
        return $user->can(TenantPermissions::USER_DELETE);
    }

    public function activate($user, $model): bool
    {
        return $user->can(TenantPermissions::USER_UPDATE);
    }

    public function deactivate($user, $model): bool
    {
        return $user->can(TenantPermissions::USER_UPDATE);
    }

    protected function permissionMap(): array
    {
        return [
            'viewAny' => TenantPermissions::USER_VIEW_ANY,
            'view' => TenantPermissions::USER_VIEW,
            'create' => TenantPermissions::USER_CREATE,
            'update' => TenantPermissions::USER_UPDATE,
            'delete' => TenantPermissions::USER_DELETE,
            'deleteAny' => TenantPermissions::USER_DELETE,
            'forceDelete' => TenantPermissions::USER_FORCE_DELETE,
            'forceDeleteAny' => TenantPermissions::USER_FORCE_DELETE,
            'restore' => TenantPermissions::USER_RESTORE,
            'restoreAny' => TenantPermissions::USER_RESTORE,
        ];
    }
}
