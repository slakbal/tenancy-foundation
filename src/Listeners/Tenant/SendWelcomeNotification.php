<?php

namespace Slakbal\TenancyFoundation\Listeners\Tenant;

use Slakbal\TenancyFoundation\Events\Tenant\TenantRegistered;

class SendWelcomeNotification
{
    public function handle(TenantRegistered $event): void
    {
        $notification = config('tenancy-foundation.notifications.welcome');

        if ($notification === null) {
            return;
        }

        $event->owner->notify(new $notification($event->tenant));
    }
}
