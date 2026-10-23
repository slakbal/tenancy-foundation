<?php

namespace Slakbal\TenancyFoundation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum FilamentPanel: string implements HasColor, HasIcon, HasLabel
{
    case ADMIN = 'admin';
    case TENANT = 'app';

    public function getPanelId()
    {
        return match ($this) {
            self::ADMIN => 'admin',
            self::TENANT => 'app',
        };
    }

    public function getPath(): string
    {
        return match ($this) {
            self::ADMIN => 'admin',
            self::TENANT => '',
        };
    }

    // Convenience Delegation method
    public function getAuthGuard(): string
    {
        return match ($this) {
            self::ADMIN => AuthGuard::ADMIN->getAuthGuard(),
            self::TENANT => AuthGuard::TENANT->getAuthGuard(),
        };
    }

    // Convenience Delegation method
    public function getPasswordBroker(): string
    {
        return match ($this) {
            self::ADMIN => AuthGuard::ADMIN->getPasswordBroker(),
            self::TENANT => AuthGuard::TENANT->getPasswordBroker(),
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::ADMIN => __('Admin Panel'),
            self::TENANT => __('Tenant Panel'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ADMIN => 'danger',
            self::TENANT => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::ADMIN => 'heroicon-o-user-plus',
            self::TENANT => 'heroicon-o-user-minus',
        };
    }
}
