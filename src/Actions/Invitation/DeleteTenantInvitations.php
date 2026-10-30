<?php

namespace Slakbal\TenancyFoundation\Actions\Invitation;

use Illuminate\Database\Eloquent\Model;

readonly class DeleteTenantInvitations
{
    public function handle(Model $tenant): int
    {
        return config('tenancy-foundation.models.invitation')::query()
            ->where('tenant_id', $tenant->getKey())
            ->delete();
    }
}
