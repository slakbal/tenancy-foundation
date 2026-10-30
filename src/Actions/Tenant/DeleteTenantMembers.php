<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

readonly class DeleteTenantMembers
{
    public function handle(Model $tenant): void
    {
        DB::transaction(function () use ($tenant): void {
            $exclusive = $tenant->members()
                ->withTrashed()
                ->whereDoesntHave('tenants', fn ($query) => $query->whereKeyNot($tenant->getKey()))
                ->get();

            $tenant->members()->detach();

            $exclusive->each->forceDelete();
        });
    }
}
