<?php

namespace Slakbal\TenancyFoundation\Listeners\Tenant;

use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Events\Tenant\TenantMemberRemoved;

class SendMemberRemovedNotification
{
    public function handle(TenantMemberRemoved $event): void
    {
        $notification = config('tenancy-foundation.notifications.member_removed');

        if ($notification === null || $event->leftVoluntarily()) {
            return;
        }

        // On demand route, because the user may have been soft deleted and a queued notifiable would no longer load.
        Notification::route('mail', $event->user->email)->notify(new $notification($event->tenant));
    }
}
