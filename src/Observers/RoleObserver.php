<?php

namespace Slakbal\TenancyFoundation\Observers;

use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Invitation\ReleaseRoleInvitations;

class RoleObserver
{
    public function __construct(private ReleaseRoleInvitations $releaseRoleInvitations) {}

    public function deleting(Model $role): void
    {
        $this->releaseRoleInvitations->handle($role);
    }
}
