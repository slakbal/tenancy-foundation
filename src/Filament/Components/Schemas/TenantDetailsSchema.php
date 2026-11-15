<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\TenantResolution;

class TenantDetailsSchema
{
    /**
     * @return array<int, Component>
     */
    public static function components(): array
    {
        return [static::name(), static::address()];
    }

    public static function name(): TextInput
    {
        return TextInput::make('name')
            ->label(__('Tenant name'))
            ->required()
            ->maxLength(60)
            ->live(onBlur: true)
            ->afterStateUpdated(function (?string $state, callable $set): void {
                if (TenantResolution::current() === TenantResolution::SUBDOMAIN) {
                    $set('subdomain', Str::slug((string) $state));
                }
            })
            ->autofocus();
    }

    public static function address(bool $ignoreRecord = false): TextInput
    {
        return match (TenantResolution::current()) {
            TenantResolution::SUBDOMAIN => static::subdomain($ignoreRecord),
            TenantResolution::DOMAIN => static::domain($ignoreRecord),
        };
    }

    public static function subdomain(bool $ignoreRecord = false): TextInput
    {
        return TextInput::make('subdomain')
            ->label(__('Subdomain'))
            ->required()
            ->regex('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/')
            ->maxLength(63)
            ->unique(config('tenancy-foundation.models.tenant'), 'subdomain', ignoreRecord: $ignoreRecord)
            ->prefix('https://')
            ->suffix('.'.config('tenancy-foundation.domain'))
            ->notIn(TenantResolution::reservedSubdomains());
    }

    public static function domain(bool $ignoreRecord = false): TextInput
    {
        return TextInput::make('domain')
            ->label(__('Domain'))
            ->required()
            ->regex('/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/')
            ->maxLength(253)
            ->unique(config('tenancy-foundation.models.tenant'), 'domain', ignoreRecord: $ignoreRecord)
            ->prefix('https://')
            ->notIn(TenantResolution::reservedHosts())
            ->placeholder('academy.example.com');
    }

    public static function url(Model $tenant): string
    {
        return TenantResolution::current()->urlFor($tenant);
    }
}
