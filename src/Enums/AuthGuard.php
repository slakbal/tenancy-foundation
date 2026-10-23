<?php

namespace Slakbal\TenancyFoundation\Enums;

enum AuthGuard: string
{
    case ADMIN = 'admin';
    case TENANT = 'web';

    public function getAuthGuard(): string
    {
        return $this->value;
    }

    public function getPasswordBroker(): string
    {
        return match ($this) {
            self::ADMIN => 'admins',
            self::TENANT => 'users',
        };
    }
}
