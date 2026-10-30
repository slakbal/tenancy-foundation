<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;
use Slakbal\TenancyFoundation\Events\TenantDeleting;

readonly class DeleteTenant
{
    public function handle(Model $tenant): void
    {
        throw_unless($tenant->isDueForDeletion(), LogicException::class, 'The tenant is not due for deletion.');

        DB::transaction(function () use ($tenant): void {
            TenantDeleting::dispatch($tenant);

            $tenant->delete();
        });
    }
}
