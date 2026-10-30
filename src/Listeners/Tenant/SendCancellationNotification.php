<?php

namespace Slakbal\TenancyFoundation\Listeners\Tenant;

use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Events\Tenant\TenantCancelled;

class SendCancellationNotification
{
    public function handle(TenantCancelled $event): void
    {
        $notification = config('tenancy-foundation.notifications.cancelled');

        if ($notification === null) {
            return;
        }

        Notification::send(
            $event->tenant->members()->wherePivot('is_owner', true)->get(),
            new $notification($event->tenant),
        );
    }
}
