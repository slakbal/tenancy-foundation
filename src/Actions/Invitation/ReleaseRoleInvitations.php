<?php

namespace Slakbal\TenancyFoundation\Actions\Invitation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

readonly class ReleaseRoleInvitations
{
    public function handle(Model $role): void
    {
        DB::transaction(function () use ($role): void {
            $invitations = config('tenancy-foundation.models.invitation')::query()->where('role_id', $role->getKey());

            (clone $invitations)->pending()->delete();
            $invitations->update(['role_id' => null]);
        });
    }
}
