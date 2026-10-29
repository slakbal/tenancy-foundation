<?php

namespace Slakbal\TenancyFoundation\Enums\Authorisation;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AdminRoles: string implements HasColor, HasIcon, HasLabel
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case SUPPORT = 'support';

    public function getLabel(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => __('Super Admin'),
            self::ADMIN => __('Admin'),
            self::SUPPORT => __('Support'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'warning',
            self::ADMIN => 'primary',
            self::SUPPORT => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'heroicon-o-shield-check',
            self::ADMIN => 'heroicon-o-user-group',
            self::SUPPORT => 'heroicon-o-lifebuoy',
        };
    }
}
