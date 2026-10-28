<?php

namespace Slakbal\TenancyFoundation\Policies\Admin;

use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Policies\AuthorisationPolicy;

class ContactRequestPolicy extends AuthorisationPolicy
{
    protected function permissionMap(): array
    {
        return [
            'viewAny' => AdminPermissions::CONTACT_REQUEST_VIEW_ANY,
            'view' => AdminPermissions::CONTACT_REQUEST_VIEW,
            'update' => AdminPermissions::CONTACT_REQUEST_UPDATE,
            'delete' => AdminPermissions::CONTACT_REQUEST_DELETE,
            'deleteAny' => AdminPermissions::CONTACT_REQUEST_DELETE,
        ];
    }

    public function respond($user, $contactRequest): bool
    {
        return $user->can(AdminPermissions::CONTACT_REQUEST_RESPOND) && $contactRequest->canBeRespondedTo();
    }

    public function dismiss($user, $contactRequest): bool
    {
        return $user->can(AdminPermissions::CONTACT_REQUEST_UPDATE) && $contactRequest->canBeDismissed();
    }
}
