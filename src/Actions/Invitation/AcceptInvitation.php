<?php

namespace Slakbal\TenancyFoundation\Actions\Invitation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Slakbal\TenancyFoundation\Events\Invitation\InvitationAccepted;
use Slakbal\TenancyFoundation\Exceptions\InvalidInvitation;

readonly class AcceptInvitation
{
    /**
     * @param  array<string, string>  $registrationData
     */
    public function handle(Model $invitation, array $registrationData = []): Model
    {
        return DB::transaction(function () use ($invitation, $registrationData): Model {
            $current = $invitation->newQuery()->lockForUpdate()->findOrFail($invitation->getKey());

            throw_unless($this->isPending($current), InvalidInvitation::notPending());

            $user = $this->resolveUser($invitation, $registrationData);

            $invitation->tenant->members()->syncWithoutDetaching([
                $user->getKey() => [
                    'is_owner' => false,
                    'is_default_tenant' => ! $user->tenants()->exists(),
                ],
            ]);

            if ($invitation->role) {
                setPermissionsTeamId($invitation->tenant_id);
                $user->assignRole($invitation->role);
            }

            $invitation->forceFill(['accepted_at' => now()])->save();

            DB::afterCommit(fn () => InvitationAccepted::dispatch($invitation, $user));

            return $user;
        });
    }

    protected function isPending(Model $invitation): bool
    {
        return $invitation->accepted_at === null && $invitation->expires_at?->isFuture() === true;
    }

    /**
     * @param  array<string, string>  $registrationData
     */
    protected function resolveUser(Model $invitation, array $registrationData): Model
    {
        $userModel = config('tenancy-foundation.models.user');

        $user = $userModel::withTrashed()->where('email', $invitation->email)->first();

        throw_if($user?->trashed(), InvalidInvitation::deletedAccount());

        return $user ?? tap($userModel::query()->create([
            ...Arr::only($registrationData, config('tenancy-foundation.user_name_attributes')),
            'email' => $invitation->email,
            'password' => Hash::make($registrationData['password']),
        ]))->markEmailAsVerified();
    }
}
