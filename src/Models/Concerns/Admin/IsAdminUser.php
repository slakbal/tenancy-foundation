<?php

namespace Slakbal\TenancyFoundation\Models\Concerns\Admin;

use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Filament\Panel;

trait IsAdminUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return $this instanceof self
            && $panel->getAuthGuard() === AuthGuard::ADMIN->getAuthGuard()
            && $this->guard_name === AuthGuard::ADMIN->value;

        // todo also do checks so that e.g. models have a specific role or permission to access the admin panel, e.g. 'admin' role or 'access admin panel' permission
    }
}
