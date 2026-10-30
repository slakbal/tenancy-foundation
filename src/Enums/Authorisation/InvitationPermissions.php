<?php

namespace Slakbal\TenancyFoundation\Enums\Authorisation;

enum InvitationPermissions: string
{
    case INVITATION_VIEW_ANY = 'invitation.view_any';
    case INVITATION_VIEW = 'invitation.view';
    case INVITATION_CREATE = 'invitation.create';
    case INVITATION_DELETE = 'invitation.delete';
}
