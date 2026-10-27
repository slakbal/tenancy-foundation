<?php

namespace Slakbal\TenancyFoundation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TenantStatus: string implements HasColor, HasIcon, HasLabel
{
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => __('Active'),
            self::CANCELLED => __('Cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::ACTIVE => Heroicon::OutlinedCheckCircle,
            self::CANCELLED => Heroicon::OutlinedXCircle,
        };
    }
}
