<?php

namespace Slakbal\TenancyFoundation\Listeners\Tenant;

use Slakbal\TenancyFoundation\Actions\Invitation\DeleteTenantInvitations;
use Slakbal\TenancyFoundation\Events\Tenant\TenantDeleting;

class DeletedTenant
{
    /**
     * The package's own cleanup. The application cleans up its data in its own listener for TenantDeleting.
     *
     * @return array<int, class-string>
     */
    protected function actions(): array
    {
        return array_filter([
            DeleteTenantInvitations::class,
        ]);
    }

    public function handle(TenantDeleting $event): void
    {
        foreach ($this->actions() as $action) {
            info("Package Delete: Running cleanup action: {$action}");
            app($action)->handle($event->tenant);
        }
    }
}
