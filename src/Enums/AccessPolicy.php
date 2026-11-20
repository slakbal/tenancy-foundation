<?php

namespace Slakbal\TenancyFoundation\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum AccessPolicy: string implements HasDescription, HasLabel
{
    case OPEN_EXCEPT_BLOCKED = 'open_except_blocked';
    case ALLOW_LIST_ONLY = 'allow_list_only';

    public function getDescription(): string
    {
        return match ($this) {
            self::OPEN_EXCEPT_BLOCKED => __('Everyone may sign in, except blocked domains and email addresses. An allow rule for an email address lets that person in even when their domain is blocked.'),
            self::ALLOW_LIST_ONLY => __('Only allowed domains and email addresses may sign in, everyone else is refused. A block rule for an email address keeps that person out even when their domain is allowed.'),
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::OPEN_EXCEPT_BLOCKED => __('Open, except blocked domains'),
            self::ALLOW_LIST_ONLY => __('Allowed domains only'),
        };
    }
}
