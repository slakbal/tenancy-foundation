<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Columns;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;

class RoleBadgeColumn
{
    /**
     * @param  class-string<TenantRoles|AdminRoles>  $enum
     */
    public static function make(string $name = 'roles.name', string $enum = TenantRoles::class, ?string $label = null): TextColumn
    {
        return TextColumn::make($name)
            ->label($label ?? __('Tenant role'))
            ->badge()
            ->formatStateUsing(fn (string $state): string => $enum::tryFrom($state)?->getLabel() ?? Str::headline($state))
            ->color(fn (string $state): string => $enum::tryFrom($state)?->getColor() ?? 'gray')
            ->icon(fn (string $state): ?string => $enum::tryFrom($state)?->getIcon());
    }
}
