<?php

namespace Slakbal\TenancyFoundation\Policies\Admin;

use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Policies\AuthorisationPolicy;

class TenantPolicy extends AuthorisationPolicy
{
    protected function permissionMap(): array
    {
        return [
            'viewAny' => AdminPermissions::TENANT_VIEW_ANY,
            'view' => AdminPermissions::TENANT_VIEW,
            'update' => AdminPermissions::TENANT_UPDATE,
        ];
    }

    public function cancel($user, $tenant): bool
    {
        return $user->can(AdminPermissions::TENANT_CANCEL);
    }

    public function reactivate($user, $tenant): bool
    {
        return $user->can(AdminPermissions::TENANT_REACTIVATE);
    }
}
