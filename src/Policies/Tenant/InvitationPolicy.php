<?php

namespace Slakbal\TenancyFoundation\Policies\Tenant;

use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\Authorisation\InvitationPermissions;
use Slakbal\TenancyFoundation\Policies\AuthorisationPolicy;

class InvitationPolicy extends AuthorisationPolicy
{
    public function viewAny($user): bool
    {
        return $user->can(InvitationPermissions::INVITATION_CREATE) && parent::viewAny($user);
    }

    public function view($user, $invitation): bool
    {
        return $user->can(InvitationPermissions::INVITATION_CREATE) && parent::view($user, $invitation);
    }

    public function delete($user, $invitation): bool
    {
        if ($invitation instanceof Model && ! is_null($invitation->accepted_at)) {
            return false;
        }

        return $user->can(InvitationPermissions::INVITATION_DELETE);
    }

    protected function permissionMap(): array
    {
        return [
            'viewAny' => InvitationPermissions::INVITATION_VIEW_ANY,
            'view' => InvitationPermissions::INVITATION_VIEW,
            'create' => InvitationPermissions::INVITATION_CREATE,
            'deleteAny' => InvitationPermissions::INVITATION_DELETE,
            'forceDelete' => InvitationPermissions::INVITATION_DELETE,
            'forceDeleteAny' => InvitationPermissions::INVITATION_DELETE,
        ];
    }
}
