<?php

namespace Slakbal\TenancyFoundation\Events\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class AdminUserCreated
{
    use Dispatchable;

    public function __construct(public Model $admin) {}
}
