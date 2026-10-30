<?php

namespace Slakbal\TenancyFoundation\Listeners\Invitation;

use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Events\Invitation\InvitationIssued;

class SendInvitationNotification
{
    public function handle(InvitationIssued $event): void
    {
        $notification = config('tenancy-foundation.notifications.invitation_created');

        if ($notification === null) {
            return;
        }

        Notification::route('mail', $event->invitation->email)
            ->notify(new $notification($event->invitation));
    }
}
