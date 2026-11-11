<?php

namespace Slakbal\TenancyFoundation\Exceptions;

use RuntimeException;

class InvalidInvitation extends RuntimeException
{
    public static function notAllowed(string $email): self
    {
        return new self(__(':email cannot be invited to this tenant.', ['email' => $email]));
    }

    public static function ownerRole(): self
    {
        return new self(__('The owner role cannot be given through an invitation.'));
    }

    public static function alreadyMember(string $email): self
    {
        return new self(__(':email already belongs to this tenant.', ['email' => $email]));
    }

    public static function notPending(): self
    {
        return new self(__('This invitation was already accepted or has expired.'));
    }

    public static function deletedAccount(): self
    {
        return new self(__('The account of this email was deleted. Restore the user from the users list instead.'));
    }

    public static function signInRequired(): self
    {
        return new self(__('Sign in with the invited account to accept this invitation.'));
    }
}
