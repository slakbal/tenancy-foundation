<?php

namespace Slakbal\TenancyFoundation\Listeners\Tenant;

use Slakbal\TenancyFoundation\Actions\Invitation\RevokePendingInvitations;
use Slakbal\TenancyFoundation\Events\Tenant\TenantCancelled;

class CancelledTenant
{
    /**
     * The package's own cleanup. The application cleans up its data in its own listener for TenantCancelled.
     *
     * @return array<int, class-string>
     */
    protected function actions(): array
    {
        return array_filter([
            RevokePendingInvitations::class,
        ]);
    }

    public function handle(TenantCancelled $event): void
    {
        foreach ($this->actions() as $action) {
            info("Package Cancel: Running cleanup action: {$action}");
            app($action)->handle($event->tenant);
        }
    }
}
