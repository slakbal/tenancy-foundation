<?php

namespace Slakbal\TenancyFoundation\Models\Concerns\Admin;

use Filament\Panel;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\AuthGuard;

trait IsAdminUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return $this instanceof self
            && $panel->getAuthGuard() === AuthGuard::ADMIN->getAuthGuard()
            && $this->guard_name === AuthGuard::ADMIN->value
            && in_array($this->status, [AdminStatus::ACTIVE, AdminStatus::PENDING], true)
            && ! $this->trashed();

        // A pending admin passes so the set password link (password_auth) or the app's first sign in (SSO) can activate them.
    }
}
