<?php

namespace Slakbal\TenancyFoundation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum MemberStatus: string implements HasColor, HasIcon, HasLabel
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => __('Active'),
            self::INACTIVE => __('Inactive'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::ACTIVE => Heroicon::OutlinedCheckCircle,
            self::INACTIVE => Heroicon::OutlinedPauseCircle,
        };
    }
}
