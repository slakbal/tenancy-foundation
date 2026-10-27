<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;

class TenantDetailsSchema
{
    /**
     * @return array<int, Component>
     */
    public static function components(): array
    {
        return [static::name(), static::subdomain()];
    }

    public static function name(): TextInput
    {
        return TextInput::make('name')
            ->label(__('Tenant name'))
            ->required()
            ->maxLength(60)
            ->live(onBlur: true)
            ->afterStateUpdated(fn (?string $state, callable $set) => $set('subdomain', Str::slug((string) $state)))
            ->autofocus();
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
            ->notIn(config('tenancy-foundation.reserved_subdomains'));
    }

    public static function url(?string $subdomain): string
    {
        return appDomainScheme().$subdomain.'.'.config('tenancy-foundation.domain');
    }
}
