<?php

namespace Slakbal\TenancyFoundation\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;

enum TenantResolution: string implements HasLabel
{
    case SUBDOMAIN = 'subdomain';
    case DOMAIN = 'domain';

    public const string HOST_TENANT = 'tenancy-foundation.host-tenant';

    public static function current(): self
    {
        $resolution = config('tenancy-foundation.resolution', self::SUBDOMAIN);

        return $resolution instanceof self ? $resolution : self::from($resolution);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::SUBDOMAIN => __('Subdomain'),
            self::DOMAIN => __('Domain'),
        };
    }

    public function attribute(): string
    {
        return $this->value;
    }

    public function routeDomain(): string
    {
        return match ($this) {
            self::SUBDOMAIN => '{tenant:subdomain}.'.config('tenancy-foundation.domain'),
            self::DOMAIN => '{tenant:domain}',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function reservedHosts(): array
    {
        return array_values(array_unique(array_filter([config('tenancy-foundation.domain'), FilamentPanel::ADMIN->getDomain()])));
    }

    /**
     * @return array<int, string>
     */
    public static function reservedSubdomains(): array
    {
        $suffix = '.'.config('tenancy-foundation.domain');

        $hosts = array_filter(self::reservedHosts(), fn (string $host): bool => str_ends_with($host, $suffix));

        return array_values(array_unique([
            ...config('tenancy-foundation.reserved_subdomains', []),
            ...array_map(fn (string $host): string => Str::beforeLast($host, $suffix), $hosts),
        ]));
    }

    public function routePattern(): string
    {
        $reserved = array_map(fn (string $host): string => preg_quote($host, '#'), self::reservedHosts());

        return ($reserved === [] ? '' : '(?!(?:'.implode('|', $reserved).')$)').'[a-z0-9.\-]+';
    }

    public function host(?string $value): string
    {
        return match ($this) {
            self::SUBDOMAIN => $value.'.'.config('tenancy-foundation.domain'),
            self::DOMAIN => (string) $value,
        };
    }

    public function tenantForHost(string $host): ?Model
    {
        $resolved = self::hostTenant();

        if ($resolved !== null && $this->hostFor($resolved) === $host) {
            return $resolved;
        }

        $value = $this === self::DOMAIN ? $host : self::subdomainOf($host);

        if ($value === null) {
            return null;
        }

        return config('tenancy-foundation.models.tenant')::query()->where($this->attribute(), $value)->first();
    }

    // Filament's tenant lookup by route key (resolveTenantUsing), reusing the tenant EnsureKnownTenantHost resolved.
    public function tenantForKey(string $key): Model
    {
        $resolved = self::hostTenant();

        if ($resolved !== null && $resolved->getAttribute($this->attribute()) === $key) {
            return $resolved;
        }

        return config('tenancy-foundation.models.tenant')::query()->where($this->attribute(), $key)->firstOrFail();
    }

    // The tenant of the current request's host, kept by EnsureKnownTenantHost so the host is looked up once per request.
    public static function rememberHostTenant(Request $request, Model $tenant): void
    {
        $request->attributes->set(self::HOST_TENANT, $tenant);
    }

    protected static function hostTenant(): ?Model
    {
        $tenant = request()->attributes->get(self::HOST_TENANT);

        return $tenant instanceof Model ? $tenant : null;
    }

    // The tenant label of a host under `domain`; null for other hosts and reserved subdomains.
    protected static function subdomainOf(string $host): ?string
    {
        $suffix = '.'.config('tenancy-foundation.domain');

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $subdomain = Str::beforeLast($host, $suffix);

        return in_array($subdomain, self::reservedSubdomains(), true) ? null : $subdomain;
    }

    public function hostFor(Model $tenant): string
    {
        return $this->host($tenant->getAttribute($this->attribute()));
    }

    public function urlFor(Model $tenant): string
    {
        // Same scheme and port as every other generated URL (forced https, APP_URL port), only the host differs.
        return Uri::of(URL::to('/'))->withHost($this->hostFor($tenant))->value();
    }
}
