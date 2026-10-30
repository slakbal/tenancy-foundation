<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Enums\AuthGuard;

readonly class DeleteTenantPermissionData
{
    public function handle(Model $tenant): void
    {
        $team = config('permission.column_names.team_foreign_key');
        $userType = (new (config('tenancy-foundation.models.user')))->getMorphClass();

        DB::transaction(function () use ($tenant, $team, $userType): void {
            collect(['model_has_roles', 'model_has_permissions'])
                ->each(fn (string $table) => DB::table(config("permission.table_names.{$table}"))
                    ->where($team, $tenant->getKey())
                    ->where('model_type', $userType)
                    ->delete());

            DB::table(config('permission.table_names.roles'))
                ->where($team, $tenant->getKey())
                ->where('guard_name', AuthGuard::TENANT->value)
                ->delete();
        });
    }
}
