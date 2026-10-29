<?php

namespace Slakbal\TenancyFoundation\Enums\Authorisation;

enum AdminPermissions: string
{
    // Impersonate Users
    case IMPERSONATE_USER = 'impersonate.user';

    // Admin Panel Users (for the users in the admins table)
    case ADMIN_USER_VIEW_ANY = 'admin_user.view_any';
    case ADMIN_USER_VIEW = 'admin_user.view';
    case ADMIN_USER_CREATE = 'admin_user.create';
    case ADMIN_USER_UPDATE = 'admin_user.update';
    case ADMIN_USER_DELETE = 'admin_user.delete';
    case ADMIN_USER_RESTORE = 'admin_user.restore';
    case ADMIN_USER_FORCE_DELETE = 'admin_user.force_delete';

    // Tenants
    case TENANT_VIEW_ANY = 'tenant.view_any';
    case TENANT_VIEW = 'tenant.view';
    case TENANT_UPDATE = 'tenant.update';
    case TENANT_CANCEL = 'tenant.cancel';
    case TENANT_REACTIVATE = 'tenant.reactivate';

    // Contact Requests
    case CONTACT_REQUEST_VIEW_ANY = 'contact_request.view_any';
    case CONTACT_REQUEST_VIEW = 'contact_request.view';
    case CONTACT_REQUEST_UPDATE = 'contact_request.update';
    case CONTACT_REQUEST_RESPOND = 'contact_request.respond';
    case CONTACT_REQUEST_DELETE = 'contact_request.delete';

    // Tenant Users (for the users in the users table)
    case USER_VIEW_ANY = 'user.view_any';
    case USER_VIEW = 'user.view';
    case USER_CREATE = 'user.create';
    case USER_UPDATE = 'user.update';
    case USER_DELETE = 'user.delete';
    case USER_RESTORE = 'user.restore';
    case USER_FORCE_DELETE = 'user.force_delete';
}
