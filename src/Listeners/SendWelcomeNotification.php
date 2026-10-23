<?php

namespace Slakbal\TenancyFoundation\Listeners;

use Slakbal\TenancyFoundation\Events\TenantRegistered;

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
