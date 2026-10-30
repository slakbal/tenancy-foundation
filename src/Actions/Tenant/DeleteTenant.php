<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;
use Slakbal\TenancyFoundation\Events\Tenant\TenantDeleting;

readonly class DeleteTenant
{
    public function __construct(
        private DeleteTenantMembers $deleteTenantMembers,
        private DeleteTenantPermissionData $deleteTenantPermissionData,
    ) {}

    public function handle(Model $tenant): void
    {
        throw_unless($tenant->isDueForDeletion(), LogicException::class, 'The tenant is not due for deletion.');

        DB::transaction(function () use ($tenant): void {
            // Listeners clean up the data they own while the tenant and its members still exist
            TenantDeleting::dispatch($tenant);

            $this->deleteTenantMembers->handle($tenant);
            $this->deleteTenantPermissionData->handle($tenant);

            $tenant->delete();
        });
    }
}
