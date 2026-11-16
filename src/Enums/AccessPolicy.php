<?php

namespace Slakbal\TenancyFoundation\Enums;

use Filament\Support\Contracts\HasLabel;

enum AccessPolicy: string implements HasLabel
{
    case OPEN_EXCEPT_BLOCKED = 'open_except_blocked';
    case ALLOW_LIST_ONLY = 'allow_list_only';

    public function getLabel(): string
    {
        return match ($this) {
            self::OPEN_EXCEPT_BLOCKED => __('Open, except blocked domains'),
            self::ALLOW_LIST_ONLY => __('Allowed domains only'),
        };
    }
}
