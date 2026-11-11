<?php

namespace Slakbal\TenancyFoundation\Policies\Tenant;

use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Policies\AuthorisationPolicy;

/**
 * Rules for the users of the current tenant.
 *
 * Owners bypass every rule through the owner Gate::before, so the rules below bind everybody else: nobody who is not an
 * owner may touch an owner, view only members have no write permission at all and the tenant panel never force deletes (no permission maps to it and TenantForceDeleteGuard
 * denies owners too).
 * The actions repeat the owner and self checks that matter for an owner acting, so the guards hold without the policy.
 */
class UserPolicy extends AuthorisationPolicy
{
    public function update($user, $model): bool
    {
        return parent::update($user, $model) && ! $this->isOwner($model);
    }

    public function delete($user, $model): bool
    {
        return parent::delete($user, $model) && ! $this->isOwner($model);
    }

    public function removeMember($user, $model): bool
    {
        return $user->can(TenantPermissions::USER_DELETE) && ! $this->isOwner($model) && ! $this->isSelf($user, $model);
    }

    public function changeRole($user, $model): bool
    {
        return $user->can(TenantPermissions::USER_UPDATE) && ! $this->isOwner($model) && ! $this->isSelf($user, $model);
    }

    public function activate($user, $model): bool
    {
        return $this->changeRole($user, $model);
    }

    public function deactivate($user, $model): bool
    {
        return $this->changeRole($user, $model);
    }

    /**
     * Any member may leave a tenant they belong to, except an owner, who has to hand over or give up ownership first.
     */
    public function leave($user, $model): bool
    {
        return $this->isSelf($user, $model) && ! $this->isOwner($model);
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
            'restore' => TenantPermissions::USER_RESTORE,
            'restoreAny' => TenantPermissions::USER_RESTORE,
        ];
    }

    protected function isOwner($model): bool
    {
        return $model instanceof Model && method_exists($model, 'isOwner') && $model->isOwner();
    }

    protected function isSelf($user, $model): bool
    {
        return $model instanceof Model && $user instanceof Model && $user->is($model);
    }
}
