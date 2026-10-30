<?php

namespace Slakbal\TenancyFoundation\Actions\Invitation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Events\InvitationIssued;
use Slakbal\TenancyFoundation\Exceptions\InvalidInvitation;

class CreateInvitation
{
    public function handle(Model $tenant, ?Model $inviter, string $email, Model $role): Model
    {
        $email = mb_strtolower($email);

        throw_if($role->name === TenantRoles::OWNER->value, InvalidInvitation::ownerRole());
        throw_if($tenant->members()->where('email', $email)->exists(), InvalidInvitation::alreadyMember($email));

        $invitation = DB::transaction(fn (): Model => config('tenancy-foundation.models.invitation')::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'email' => $email,
                'accepted_at' => null,
            ],
            [
                'invited_by' => $inviter?->getKey(),
                'role_id' => $role->getKey(),
                'token' => Str::random(64),
                'expires_at' => now()->addDays((int) config('tenancy-foundation.invitations.expiry_days')),
            ],
        ));

        InvitationIssued::dispatch($invitation);

        return $invitation;
    }
}
