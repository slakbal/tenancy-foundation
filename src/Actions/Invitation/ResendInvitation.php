<?php

namespace Slakbal\TenancyFoundation\Actions\Invitation;

use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Exceptions\InvalidInvitation;

readonly class ResendInvitation
{
    /**
     * Issues a fresh link and expiry for an invitation that has not been accepted (also an expired one) and mails it again.
     */
    public function handle(Model $invitation): Model
    {
        throw_if($invitation->accepted_at !== null, InvalidInvitation::notPending());

        return $invitation->issue();
    }
}
