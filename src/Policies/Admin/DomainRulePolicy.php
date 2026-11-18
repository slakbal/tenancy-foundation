<?php

namespace Slakbal\TenancyFoundation\Policies\Admin;

use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Policies\AuthorisationPolicy;

// Access rules are part of the tenant (no resource of their own), so the tenant permissions guard them.
class DomainRulePolicy extends AuthorisationPolicy
{
    protected function permissionMap(): array
    {
        return [
            'viewAny' => AdminPermissions::TENANT_VIEW,
            'view' => AdminPermissions::TENANT_VIEW,
            'create' => AdminPermissions::TENANT_UPDATE,
            'update' => AdminPermissions::TENANT_UPDATE,
            'delete' => AdminPermissions::TENANT_UPDATE,
            'deleteAny' => AdminPermissions::TENANT_UPDATE,
        ];
    }
}
