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

        // A pending admin has only a random password, so cannot sign in, but must pass this check for Filament to accept the password reset link

        // todo also do checks so that e.g. models have a specific role or permission to access the admin panel, e.g. 'admin' role or 'access admin panel' permission
    }
}
