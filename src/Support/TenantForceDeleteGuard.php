<?php

namespace Slakbal\TenancyFoundation\Support;

use Illuminate\Auth\Access\Response;
use Illuminate\Support\Arr;

/**
 * Users are never permanently deleted from the tenant panel, not even by an owner who would otherwise bypass the policy.
 * Only the admin panel force deletes.
 */
class TenantForceDeleteGuard
{
    /**
     * @param  array<int, mixed>|mixed  $arguments
     */
    public static function denial(string $ability, mixed $arguments): ?Response
    {
        if (! in_array($ability, ['forceDelete', 'forceDeleteAny'], true)) {
            return null;
        }

        $subject = Arr::first(Arr::wrap($arguments));
        $userModel = config('tenancy-foundation.models.user');

        return is_a($subject, $userModel, true)
            ? Response::deny(__('Users cannot be permanently deleted from the tenant panel.'))
            : null;
    }
}
