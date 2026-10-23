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
        return [
            TextInput::make('name')
                ->label(__('Tenant name'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (?string $state, callable $set) => $set('subdomain', Str::slug((string) $state)))
                ->autofocus(),

            TextInput::make('subdomain')
                ->label(__('Subdomain'))
                ->required()
                ->regex('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/')
                ->maxLength(63)
                ->unique(config('tenancy-foundation.models.tenant'), 'subdomain')
                ->prefix('https://')
                ->suffix('.'.config('tenancy-foundation.domain'))
                ->notIn(config('tenancy-foundation.reserved_subdomains')),
        ];
    }
}
