<?php

namespace Slakbal\TenancyFoundation\Support;

use Illuminate\Database\Eloquent\Model;

class UserDisplayName
{
    /**
     * The name of a user built from the configured name attributes, falling back to the email.
     */
    public static function for(Model $user): string
    {
        $name = collect(config('tenancy-foundation.user_name_attributes'))
            ->map(fn (string $attribute): mixed => $user->getAttribute($attribute))
            ->filter()
            ->implode(' ');

        return $name !== '' ? $name : (string) $user->getAttribute('email');
    }
}
