<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

readonly class DeleteTenantPermissionData
{
    public function handle(Model $tenant): void
    {
        $team = config('permission.column_names.team_foreign_key');

        DB::transaction(function () use ($tenant, $team): void {
            collect(['model_has_roles', 'model_has_permissions', 'roles'])
                ->each(fn (string $table) => DB::table(config("permission.table_names.{$table}"))->where($team, $tenant->getKey())->delete());
        });
    }
}
