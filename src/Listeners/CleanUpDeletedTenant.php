<?php

namespace Slakbal\TenancyFoundation\Listeners;

use Slakbal\TenancyFoundation\Actions\Tenant\DeleteTenantMembers;
use Slakbal\TenancyFoundation\Actions\Tenant\DeleteTenantPermissionData;
use Slakbal\TenancyFoundation\Events\TenantDeleting;

class CleanUpDeletedTenant
{
    /**
     * The members and the permission rows are removed last, after everything that owns data of the tenant.
     *
     * @return array<int, class-string>
     */
    protected function actions(): array
    {
        return [
            ...config('tenancy-foundation.cleanup.deleting'),
            DeleteTenantMembers::class,
            DeleteTenantPermissionData::class,
        ];
    }

    public function handle(TenantDeleting $event): void
    {
        foreach ($this->actions() as $action) {
            app($action)->handle($event->tenant);
        }
    }
}
