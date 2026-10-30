<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Slakbal\TenancyFoundation\Enums\MemberStatus;

readonly class ChangeTenantMemberStatus
{
    public function handle(Model $tenant, Model $user, MemberStatus $status, ?Model $actor = null): void
    {
        DB::transaction(function () use ($tenant, $user, $status, $actor): void {
            $membership = $tenant->members()->get()->firstWhere('id', $user->getKey());

            if (! $membership) {
                throw new InvalidArgumentException('The user is not a member of the tenant.');
            }

            if ($actor?->is($user)) {
                throw new AuthorizationException(__('You cannot change your own status.'));
            }

            if ($membership->pivot->is_owner) {
                throw new AuthorizationException(__('The status of an owner cannot be changed.'));
            }

            $tenant->members()->updateExistingPivot($user->getKey(), ['status' => $status->value]);

            $user->unsetRelations();
        });
    }
}
