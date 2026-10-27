<?php

namespace Slakbal\TenancyFoundation\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Slakbal\TenancyFoundation\Actions\DeleteTenant;

class DeleteDueTenants implements ShouldQueue
{
    use Queueable;

    public function handle(DeleteTenant $deleteTenant): void
    {
        config('tenancy-foundation.models.tenant')::query()
            ->dueForDeletion()
            ->lazyById()
            ->each(fn (Model $tenant) => rescue(fn () => $deleteTenant->handle($tenant)));
    }
}
