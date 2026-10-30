<?php

namespace Slakbal\TenancyFoundation\Actions\Invitation;

use Illuminate\Database\Eloquent\Model;

readonly class RevokePendingInvitations
{
    public function handle(Model $tenant): int
    {
        return config('tenancy-foundation.models.invitation')::query()
            ->pending()
            ->where('tenant_id', $tenant->getKey())
            ->delete();
    }
}
