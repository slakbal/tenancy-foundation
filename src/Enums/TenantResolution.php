<?php

namespace Slakbal\TenancyFoundation\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;

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

    public function routePattern(): string
    {
        $reserved = array_map(fn (string $host): string => preg_quote($host, '#'), array_filter([config('tenancy-foundation.domain')]));

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
