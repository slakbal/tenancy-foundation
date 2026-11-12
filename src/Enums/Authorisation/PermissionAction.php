<?php

namespace Slakbal\TenancyFoundation\Enums\Authorisation;

use Filament\Support\Contracts\HasLabel;

/**
 * The action suffix of a `resource.action` permission name.
 */
enum PermissionAction: string implements HasLabel
{
    case VIEW_ANY = 'view_any';
    case VIEW = 'view';
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case RESTORE = 'restore';
    case FORCE_DELETE = 'force_delete';
    case CANCEL = 'cancel';
    case REACTIVATE = 'reactivate';
    case RESPOND = 'respond';
    case SEND_PASSWORD_RESET = 'send_password_reset';
    case MANAGE = 'manage';
    // The only action of the impersonate group (`impersonate.user`)
    case USER = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW_ANY => __('View list'),
            self::VIEW => __('View'),
            self::CREATE => __('Create'),
            self::UPDATE => __('Edit'),
            self::DELETE => __('Delete'),
            self::RESTORE => __('Restore'),
            self::FORCE_DELETE => __('Delete permanently'),
            self::CANCEL => __('Cancel'),
            self::REACTIVATE => __('Reactivate'),
            self::RESPOND => __('Respond'),
            self::SEND_PASSWORD_RESET => __('Send password reset'),
            self::MANAGE => __('Manage'),
            self::USER => __('Impersonate users'),
        };
    }
}
