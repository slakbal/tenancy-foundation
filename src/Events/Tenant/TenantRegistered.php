<?php

namespace Slakbal\TenancyFoundation\Events\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class TenantRegistered
{
    use Dispatchable;

    public function __construct(public Model $tenant, public Model $owner) {}
}
