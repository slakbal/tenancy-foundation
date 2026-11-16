<?php

namespace Slakbal\TenancyFoundation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum
DomainRuleType: string implements HasColor, HasLabel
{
    case ALLOW = 'allow';
    case BLOCK = 'block';

    public function getLabel(): string
    {
        return match ($this) {
            self::ALLOW => __('Allow'),
            self::BLOCK => __('Block'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ALLOW => 'success',
            self::BLOCK => 'danger',
        };
    }
}
