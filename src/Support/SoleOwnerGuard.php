<?php

namespace Slakbal\TenancyFoundation\Support;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class SoleOwnerGuard
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

        $isSoleOwner = $model instanceof Model
            && method_exists($model, 'isSoleOwnerOfAnyTenant')
            && $model->isSoleOwnerOfAnyTenant();

        return $isSoleOwner
            ? Response::deny(__(':name is the only owner of :tenants and cannot be deleted. Make another member an owner first.', ['name' => $model->name, 'tenants' => static::tenantNames($model)]))
            : null;
    }

    protected static function tenantNames(Model $model): string
    {
        return $model->soleOwnedTenants()->pluck('name')->implode(', ');
    }
}
