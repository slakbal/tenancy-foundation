<?php

namespace Slakbal\TenancyFoundation\Observers;

use Illuminate\Database\Eloquent\Model;

class RoleObserver
{
    public function deleting(Model $role): void
    {
        $invitations = config('tenancy-foundation.models.invitation')::query()->where('role_id', $role->getKey());

        (clone $invitations)->pending()->delete();
        $invitations->update(['role_id' => null]);
    }
}
