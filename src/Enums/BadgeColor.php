<?php

namespace Slakbal\TenancyFoundation\Enums;

enum BadgeColor: string
{
    case DEFAULT = 'info';

    public function getHex(): string
    {
        return match ($this) {
            self::DEFAULT => '#3b82f6',
        };
    }
}
