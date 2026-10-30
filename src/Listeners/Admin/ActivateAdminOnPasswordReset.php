<?php

namespace Slakbal\TenancyFoundation\Listeners\Admin;

use Illuminate\Auth\Events\PasswordReset;
use Slakbal\TenancyFoundation\Enums\AdminStatus;

class ActivateAdminOnPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        $adminModel = config('tenancy-foundation.models.admin');

        if (! $event->user instanceof $adminModel || $event->user->status !== AdminStatus::PENDING) {
            return;
        }

        $event->user->forceFill([
            'status' => AdminStatus::ACTIVE,
            'email_verified_at' => $event->user->email_verified_at ?? now(),
        ])->save();
    }
}
