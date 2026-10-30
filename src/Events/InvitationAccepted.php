<?php

namespace Slakbal\TenancyFoundation\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvitationAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Model $invitation, public Model $user) {}
}
