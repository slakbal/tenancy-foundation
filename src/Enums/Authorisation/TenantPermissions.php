<?php

namespace Slakbal\TenancyFoundation\Enums\Authorisation;

enum TenantPermissions: string
{
    // TENANT
    case TENANT_VIEW_ANY = 'tenant.view_any';
    case TENANT_VIEW = 'tenant.view';
    case TENANT_CREATE = 'tenant.create';
    case TENANT_UPDATE = 'tenant.update';
    case TENANT_DELETE = 'tenant.delete';

    // USERS
    case USER_VIEW_ANY = 'user.view_any';
    case USER_VIEW = 'user.view';
    case USER_CREATE = 'user.create';
    case USER_UPDATE = 'user.update';
    case USER_DELETE = 'user.delete';
    case USER_RESTORE = 'user.restore';
    case USER_FORCE_DELETE = 'user.force_delete';

}
