<?php

namespace Slakbal\TenancyFoundation\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class TenantReactivated
{
    use Dispatchable;

    public function __construct(public Model $tenant) {}
}
