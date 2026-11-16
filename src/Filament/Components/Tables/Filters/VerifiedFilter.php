<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Filters;

use Filament\Tables\Filters\TernaryFilter;

class VerifiedFilter
{
    public static function make(string $name = 'email_verified_at'): TernaryFilter
    {
        return TernaryFilter::make($name)
            ->label(__('Verified'))
            ->nullable()
            ->visible(fn (): bool => (bool) config('tenancy-foundation.features.email_verification'));
    }
}
