<?php

namespace Slakbal\TenancyFoundation\Enums\Authorisation;

/**
 * Custom abilities of Policies\Tenant\UserPolicy. Each value is the policy method name, so the cases can be passed
 * straight to can(), authorize() and Gate::allows().
 */
enum TenantUserAbilities: string
{
    case Leave = 'leave';
    case RemoveMember = 'removeMember';
    case ChangeRole = 'changeRole';
    case Activate = 'activate';
    case Deactivate = 'deactivate';
}
