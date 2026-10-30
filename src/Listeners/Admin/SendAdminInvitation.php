<?php

namespace Slakbal\TenancyFoundation\Listeners\Admin;

use Slakbal\TenancyFoundation\Actions\Admin\InviteAdminUser;
use Slakbal\TenancyFoundation\Events\Admin\AdminUserCreated;

class SendAdminInvitation
{
    public function __construct(private InviteAdminUser $inviteAdminUser) {}

    public function handle(AdminUserCreated $event): void
    {
        $this->inviteAdminUser->handle($event->admin);
    }
}
