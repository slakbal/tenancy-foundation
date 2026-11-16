<?php

namespace Slakbal\TenancyFoundation\Enums;

enum EmailAccess: string
{
    case ALLOWED_BY_RULE = 'allowed_by_rule';
    case ALLOWED_BY_POLICY = 'allowed_by_policy';
    case BLOCKED_BY_RULE = 'blocked_by_rule';
    case BLOCKED_BY_POLICY = 'blocked_by_policy';

    public function isAllowed(): bool
    {
        return $this === self::ALLOWED_BY_RULE || $this === self::ALLOWED_BY_POLICY;
    }
}
