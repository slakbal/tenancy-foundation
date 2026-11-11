<?php

namespace Slakbal\TenancyFoundation\Support;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class TenantMembershipGuard
{
    /**
     * @param  array<int, mixed>|mixed  $arguments
     */
    public static function denial(string $ability, mixed $arguments): ?Response
    {
        if (! in_array($ability, ['delete', 'forceDelete'], true)) {
            return null;
        }

        $model = Arr::first(Arr::wrap($arguments));
        $userModel = config('tenancy-foundation.models.user');

        if (! $model instanceof $userModel || static::tenantCount($model) < 2) {
            return null;
        }

        return Response::deny(__(':name belongs to other tenants. Remove them from this tenant instead of deleting them.', ['name' => $model->name]));
    }

    public static function tenantCount(Model $user): int
    {
        return $user->loadMissing('tenants')->tenants->count();
    }
}
