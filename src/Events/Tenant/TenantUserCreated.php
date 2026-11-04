<?php

namespace Slakbal\TenancyFoundation\Events\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class TenantUserCreated
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $data  the submitted form data, including any fields an app added
     */
    public function __construct(public Model $tenant, public Model $user, public ?Model $actor, public array $data = []) {}
}
