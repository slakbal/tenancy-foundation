<?php

namespace Slakbal\TenancyFoundation\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

enum TenantResolution: string implements HasLabel
{
    case SUBDOMAIN = 'subdomain';
    case DOMAIN = 'domain';

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

    public function hostFor(Model $tenant): string
    {
        return $this->host($tenant->getAttribute($this->attribute()));
    }

    public function urlFor(Model $tenant): string
    {
        return appDomainScheme().$this->hostFor($tenant);
    }
}
