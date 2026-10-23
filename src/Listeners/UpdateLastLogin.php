<?php

namespace Slakbal\TenancyFoundation\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

class UpdateLastLogin
{
    // Fires automatically on: Illuminate\Auth\Events\Login
    public function handle(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        $user->withoutTimestamps(fn () => $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(), // Global request helper
        ])->saveQuietly());
    }
}
