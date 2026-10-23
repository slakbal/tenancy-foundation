<?php

namespace Slakbal\TenancyFoundation\Enums\Authorisation;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TenantRoles: string implements HasColor, HasIcon, HasLabel
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case VIEW_ONLY = 'view_only';

    public function getLabel(): string
    {
        return match ($this) {
            self::OWNER => __('Owner'),
            self::ADMIN => __('Admin'),
            self::VIEW_ONLY => __('View Only'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OWNER => 'warning',
            self::ADMIN => 'primary',
            self::VIEW_ONLY => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::OWNER => 'heroicon-o-shield-check',
            self::ADMIN => 'heroicon-o-user-group',
            self::VIEW_ONLY => 'heroicon-o-eye',
        };
    }
}
