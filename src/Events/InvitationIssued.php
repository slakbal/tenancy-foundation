<?php

namespace Slakbal\TenancyFoundation\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvitationIssued
{
    use Dispatchable, SerializesModels;

    public function __construct(public Model $invitation) {}
}
