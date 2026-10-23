<?php

namespace Slakbal\TenancyFoundation\Enums\Authorisation;

enum AdminPermissions: string
{
    // Impersonate Users
    case IMPERSONATE_USER = 'impersonate.user';

    // Tenant Users
    case ADMIN_USER_VIEW_ANY = 'admin_user.view_any';
    case ADMIN_USER_VIEW = 'admin_user.view';
    case ADMIN_USER_CREATE = 'admin_user.create';
    case ADMIN_USER_UPDATE = 'admin_user.update';
    case ADMIN_USER_DELETE = 'admin_user.delete';
    case ADMIN_USER_RESTORE = 'admin_user.restore';
    case ADMIN_USER_FORCE_DELETE = 'admin_user.force_delete';

    // Tenant Users
    case USER_VIEW_ANY = 'user.view_any';
    case USER_VIEW = 'user.view';
    case USER_CREATE = 'user.create';
    case USER_UPDATE = 'user.update';
    case USER_DELETE = 'user.delete';
    case USER_RESTORE = 'user.restore';
    case USER_FORCE_DELETE = 'user.force_delete';
}
