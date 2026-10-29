<?php

namespace Slakbal\TenancyFoundation\Actions\Admin;

use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\AdminStatus;

readonly class InviteAdminUser
{
    public static function isEnabled(): bool
    {
        return config('tenancy-foundation.notifications.admin_invited') !== null;
    }

    public function handle(Model $admin): bool
    {
        $notification = config('tenancy-foundation.notifications.admin_invited');

        if ($notification === null || $admin->status !== AdminStatus::PENDING) {
            return false;
        }

        $admin->notify(new $notification);

        return true;
    }
}
