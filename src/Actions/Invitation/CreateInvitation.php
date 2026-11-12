<?php

namespace Slakbal\TenancyFoundation\Actions\Invitation;

use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Events\Invitation\InvitationIssued;
use Slakbal\TenancyFoundation\Exceptions\InvalidInvitation;

class CreateInvitation
{
    public function handle(Model $tenant, ?Model $inviter, string $email, Model $role): Model
    {
        $email = mb_strtolower($email);

        throw_if($role->name === TenantRoles::OWNER->value, InvalidInvitation::ownerRole());
        throw_if($tenant->members()->where('email', $email)->exists(), InvalidInvitation::alreadyMember($email));
        throw_if(config('tenancy-foundation.models.user')::onlyTrashed()->where('email', $email)->exists(), InvalidInvitation::deletedAccount());

        throw_unless($tenant->canBeInvited($email, $role), InvalidInvitation::notAllowed($email));

        $invitation = config('tenancy-foundation.models.invitation')::query()
            ->firstOrNew(['tenant_id' => $tenant->getKey(), 'email' => $email, 'accepted_at' => null])
            ->fill(['invited_by' => $inviter?->getKey(), 'role_id' => $role->getKey()])
            ->refreshToken();

        InvitationIssued::dispatch($invitation);

        return $invitation;
    }
}
