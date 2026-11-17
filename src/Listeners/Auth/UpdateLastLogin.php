<?php

namespace Slakbal\TenancyFoundation\Listeners\Auth;

use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;

class UpdateLastLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof Model) {
            return;
        }

        $user->withoutTimestamps(fn () => $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->saveQuietly());
    }
}
