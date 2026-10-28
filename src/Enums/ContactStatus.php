<?php

namespace Slakbal\TenancyFoundation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ContactStatus: string implements HasColor, HasIcon, HasLabel
{
    case NEW = 'new';
    case DISMISSED = 'dismissed';
    case COMPLETED = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::NEW => __('New'),
            self::DISMISSED => __('Dismissed'),
            self::COMPLETED => __('Completed'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NEW => 'warning',
            self::DISMISSED => 'gray',
            self::COMPLETED => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::NEW => Heroicon::OutlinedEnvelope,
            self::DISMISSED => Heroicon::OutlinedXCircle,
            self::COMPLETED => Heroicon::OutlinedCheckCircle,
        };
    }

    public function isClosed(): bool
    {
        return $this !== self::NEW;
    }

    /**
     * @return array<int, string>
     */
    public static function closedValues(): array
    {
        return collect(self::cases())->filter->isClosed()->map->value->values()->all();
    }
}
