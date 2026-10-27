<?php

namespace Slakbal\TenancyFoundation\Support;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

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

        $model = is_array($arguments) ? ($arguments[0] ?? null) : $arguments;

        $isSoleOwner = $model instanceof Model
            && method_exists($model, 'isSoleOwnerOfAnyTenant')
            && $model->isSoleOwnerOfAnyTenant();

        return $isSoleOwner
            ? Response::deny(__(':name is the only owner of a tenant and cannot be deleted. Make another member an owner first.', ['name' => static::displayName($model)]))
            : null;
    }

    protected static function displayName(Model $model): string
    {
        $name = collect(config('tenancy-foundation.user_name_attributes'))
            ->map(fn (string $attribute): mixed => $model->getAttribute($attribute))
            ->filter()
            ->implode(' ');

        return $name !== '' ? $name : (string) $model->getAttribute('email');
    }
}
