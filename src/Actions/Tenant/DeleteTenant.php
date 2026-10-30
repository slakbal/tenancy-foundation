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

            $this->deleteInvitations($tenant);
            $this->deleteMembers($tenant);
            $this->deletePermissionData($tenant);

            $tenant->delete();
        });
    }

    protected function deleteInvitations(Model $tenant): void
    {
        if (config('tenancy-foundation.features.invitations')) {
            config('tenancy-foundation.models.invitation')::query()->where('tenant_id', $tenant->getKey())->delete();
        }
    }

    protected function deleteMembers(Model $tenant): void
    {
        $exclusive = $tenant->members()
            ->withTrashed()
            ->whereDoesntHave('tenants', fn ($query) => $query->whereKeyNot($tenant->getKey()))
            ->get();

        $tenant->members()->detach();

        $exclusive->each->forceDelete();
    }

    protected function deletePermissionData(Model $tenant): void
    {
        $team = config('permission.column_names.team_foreign_key');

        collect(['model_has_roles', 'model_has_permissions', 'roles'])
            ->each(fn (string $table) => DB::table(config("permission.table_names.{$table}"))->where($team, $tenant->getKey())->delete());
    }
}
