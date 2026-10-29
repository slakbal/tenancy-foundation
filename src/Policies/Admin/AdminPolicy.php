<?php

namespace Slakbal\TenancyFoundation\Policies\Admin;

use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Policies\AuthorisationPolicy;
use Slakbal\TenancyFoundation\Support\AdminAccountGuard;

class AdminPolicy extends AuthorisationPolicy
{
    public function update($user, $model): bool
    {
        return ! $this->isProtectedFrom($user, $model) && parent::update($user, $model);
    }

    public function delete($user, $model): bool
    {
        return ! $this->isProtectedFrom($user, $model) && parent::delete($user, $model);
    }

    public function restore($user, $model): bool
    {
        return ! $this->isProtectedFrom($user, $model) && parent::restore($user, $model);
    }

    public function forceDelete($user, $model): bool
    {
        return AdminAccountGuard::isSuperAdmin($user) && parent::forceDelete($user, $model);
    }

    public function forceDeleteAny($user): bool
    {
        return AdminAccountGuard::isSuperAdmin($user) && parent::forceDeleteAny($user);
    }

    public function activate($user, $model): bool
    {
        return $model->status === AdminStatus::INACTIVE && ! $model->trashed() && $this->update($user, $model);
    }

    public function deactivate($user, $model): bool
    {
        return $model->status === AdminStatus::ACTIVE && ! $model->trashed() && $this->update($user, $model);
    }

    public function changeRole($user, $model): bool
    {
        return ! $model->trashed() && $this->update($user, $model);
    }

    public function sendPasswordResetLink($user, $model): bool
    {
        return $model->status !== AdminStatus::PENDING && ! $model->trashed() && $this->update($user, $model);
    }

    protected function isProtectedFrom(mixed $user, mixed $model): bool
    {
        return AdminAccountGuard::isSuperAdmin($model) && ! AdminAccountGuard::isSuperAdmin($user);
    }

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
