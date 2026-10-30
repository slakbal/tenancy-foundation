<?php

namespace Slakbal\TenancyFoundation\Listeners\Tenant;

use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Events\Tenant\TenantReactivated;

class SendReactivationNotification
{
    public function handle(TenantReactivated $event): void
    {
        $notification = config('tenancy-foundation.notifications.reactivated');

        if ($notification === null) {
            return;
        }

        Notification::send(
            $event->tenant->members()->wherePivot('is_owner', true)->get(),
            new $notification($event->tenant),
        );
    }
}
