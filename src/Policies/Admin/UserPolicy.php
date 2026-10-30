<?php

namespace Slakbal\TenancyFoundation\Policies\Admin;

use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
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

    public function sendPasswordResetLink($user, $model): bool
    {
        return ! $model->trashed() && $user->can(AdminPermissions::USER_SEND_PASSWORD_RESET);
    }

    public function markEmailVerified($user, $model): bool
    {
        return $model->email_verified_at === null && ! $model->trashed() && $this->update($user, $model);
    }

    protected function permissionMap(): array
    {
        return [
            'viewAny' => AdminPermissions::USER_VIEW_ANY,
            'view' => AdminPermissions::USER_VIEW,
            'create' => AdminPermissions::USER_CREATE,
            'update' => AdminPermissions::USER_UPDATE,
            'delete' => AdminPermissions::USER_DELETE,
            'deleteAny' => AdminPermissions::USER_DELETE,
            'forceDelete' => AdminPermissions::USER_FORCE_DELETE,
            'forceDeleteAny' => AdminPermissions::USER_FORCE_DELETE,
            'restore' => AdminPermissions::USER_RESTORE,
            'restoreAny' => AdminPermissions::USER_RESTORE,
        ];
    }
}
