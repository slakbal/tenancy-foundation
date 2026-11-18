<?php

namespace Slakbal\TenancyFoundation\Support;

use Closure;
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
            ? static::deferredDenial(fn (): string => __(':name is the only owner of :tenants and cannot be deleted. Make another member an owner first.', ['name' => $model->name, 'tenants' => static::tenantNames($model)]))
            : null;
    }

    // Lists check the ability on every row, so the tenant names are only queried when the message is read.
    protected static function deferredDenial(Closure $message): Response
    {
        return new class($message) extends Response
        {
            public function __construct(private readonly Closure $resolveMessage)
            {
                parent::__construct(false, null);
            }

            public function message(): ?string
            {
                return $this->message ??= ($this->resolveMessage)();
            }
        };
    }

    protected static function tenantNames(Model $model): string
    {
        return $model->soleOwnedTenants()->pluck('name')->implode(', ');
    }
}
