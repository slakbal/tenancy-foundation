<?php

namespace Slakbal\TenancyFoundation\Listeners\Contact;

use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Events\Contact\ContactRequestAnswered;

class SendContactResponseNotification
{
    public function handle(ContactRequestAnswered $event): void
    {
        $notification = config('tenancy-foundation.notifications.contact_response');

        if ($notification === null) {
            return;
        }

        $request = $event->contactRequest;

        Notification::route('mail', [$request->email => $request->name])->notify(new $notification($request));
    }
}
