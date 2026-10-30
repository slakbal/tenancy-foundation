<?php

namespace Slakbal\TenancyFoundation\Support;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class SelfDeletionGuard
{
    /**
     * @param  array<int, mixed>|mixed  $arguments
     */
    public static function denial(mixed $user, string $ability, mixed $arguments): ?Response
    {
        if (! in_array($ability, ['delete', 'forceDelete'], true)) {
            return null;
        }

        $model = Arr::first(Arr::wrap($arguments));

        return $user instanceof Model && $model instanceof Model && $user->is($model)
            ? Response::deny(__('You cannot delete your own account.'))
            : null;
    }
}
