<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Columns;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class TenantNamesColumn
{
    public static function owned(): TextColumn
    {
        return static::make('owned_tenants', __('Owner of'), isOwner: true);
    }

    public static function member(): TextColumn
    {
        return static::make('member_tenants', __('Member of'), isOwner: false);
    }

    // Both columns read the eager loaded tenants relation, so the table loads the memberships once.
    protected static function make(string $name, string $label, bool $isOwner): TextColumn
    {
        return WrappedBadgeColumn::make($name)
            ->label($label)
            ->state(fn (Model $record): array => $record->tenants
                ->filter(fn (Model $tenant): bool => (bool) $tenant->pivot->is_owner === $isOwner)
                ->pluck('name')
                ->all())
            ->visible(fn (): bool => (bool) config('tenancy-foundation.features.multi_tenant_users'));
    }
}
