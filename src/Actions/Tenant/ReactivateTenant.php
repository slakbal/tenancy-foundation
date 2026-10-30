<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Events\Tenant\TenantReactivated;

readonly class ReactivateTenant
{
    public function handle(Model $tenant): Model
    {
        return DB::transaction(function () use ($tenant): Model {
            $tenant->refresh();

            if (! $tenant->isCancelled()) {
                return $tenant;
            }

            $tenant->forceFill(['cancelled_at' => null, 'scheduled_for_deletion_at' => null])->save();

            DB::afterCommit(fn () => TenantReactivated::dispatch($tenant));

            return $tenant;
        });
    }
}
