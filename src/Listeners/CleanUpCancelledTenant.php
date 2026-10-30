<?php

namespace Slakbal\TenancyFoundation\Listeners;

use Slakbal\TenancyFoundation\Events\TenantCancelled;

class CleanUpCancelledTenant
{
    public function handle(TenantCancelled $event): void
    {
        foreach (config('tenancy-foundation.cleanup.cancelled') as $action) {
            app($action)->handle($event->tenant);
        }
    }
}
