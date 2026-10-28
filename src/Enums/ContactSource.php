<?php

namespace Slakbal\TenancyFoundation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContactSource: string implements HasColor, HasLabel
{
    case PUBLIC = 'public';
    case TENANT = 'tenant';

    public function getLabel(): string
    {
        return match ($this) {
            self::PUBLIC => __('Public'),
            self::TENANT => __('Tenant user'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PUBLIC => 'gray',
            self::TENANT => 'primary',
        };
    }
}
