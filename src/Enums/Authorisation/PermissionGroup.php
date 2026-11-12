<?php

namespace Slakbal\TenancyFoundation\Enums\Authorisation;

use Filament\Support\Contracts\HasLabel;

/**
 * The resource prefix of a `resource.action` permission name, used to group the permission matrix.
 */
enum PermissionGroup: string implements HasLabel
{
    case IMPERSONATE = 'impersonate';
    case PERMISSIONS = 'permissions';
    case ADMIN_USER = 'admin_user';
    case TENANT = 'tenant';
    case CONTACT_REQUEST = 'contact_request';
    case USER = 'user';
    case INVITATION = 'invitation';

    public function getLabel(): string
    {
        return match ($this) {
            self::IMPERSONATE => __('Impersonation'),
            self::PERMISSIONS => __('Permissions'),
            self::ADMIN_USER => __('Admin users'),
            self::TENANT => __('Tenants'),
            self::CONTACT_REQUEST => __('Contact requests'),
            self::USER => __('Users'),
            self::INVITATION => __('Invitations'),
        };
    }
}
