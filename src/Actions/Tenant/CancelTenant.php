<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Events\Tenant\TenantCancelled;

readonly class CancelTenant
{
    public function handle(Model $tenant): Model
    {
        return DB::transaction(function () use ($tenant): Model {
            $tenant->refresh();

            if ($tenant->isCancelled()) {
                return $tenant;
            }

            $tenant->forceFill([
                'cancelled_at' => now(),
                'scheduled_for_deletion_at' => now()->addDays((int) config('tenancy-foundation.cancellation.grace_days'))->endOfDay(),
            ])->save();

            DB::afterCommit(fn () => TenantCancelled::dispatch($tenant));

            return $tenant;
        });
    }
}
