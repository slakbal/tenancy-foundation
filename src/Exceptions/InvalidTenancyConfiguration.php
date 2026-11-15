<?php

namespace Slakbal\TenancyFoundation\Exceptions;

use RuntimeException;

class InvalidTenancyConfiguration extends RuntimeException
{
    public static function missingAdminHost(): self
    {
        return new self(__('Tenant resolution "domain" needs a host for the admin panel: set ADMIN_DOMAIN or APP_DOMAIN, otherwise the admin panel would answer on every tenant host.'));
    }
}
