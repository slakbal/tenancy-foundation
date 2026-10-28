<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Columns;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;

class RoleBadgeColumn
{
    public static function make(string $name = 'roles.name'): TextColumn
    {
        return TextColumn::make($name)
            ->label(__('Tenant role'))
            ->badge()
            ->formatStateUsing(fn (string $state): string => TenantRoles::tryFrom($state)?->getLabel() ?? Str::headline($state))
            ->color(fn (string $state): string => TenantRoles::tryFrom($state)?->getColor() ?? 'gray')
            ->icon(fn (string $state): ?string => TenantRoles::tryFrom($state)?->getIcon());
    }
}
