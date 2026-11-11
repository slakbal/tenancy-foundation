<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Schemas;

use Filament\Facades\Filament;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\MemberStatus;
use Slakbal\TenancyFoundation\Filament\Components\Resources\Users\UserInfolist as BaseUserInfolist;

class UserInfolist extends BaseUserInfolist
{
    /**
     * The membership entries of the current tenant. The edit form shows the same ones in its sidebar.
     *
     * @return array<int, Component>
     */
    public static function statusEntries(): array
    {
        return [
            TextEntry::make('membership_status')
                ->label(__('Status'))
                ->inlineLabel()
                ->badge()
                ->state(fn (Model $record): ?MemberStatus => $record->memberStatus()),
            TextEntry::make('tenant_role')
                ->label(__('Roles'))
                ->inlineLabel()
                ->badge()
                ->state(fn (Model $record): array => $record->roles->pluck('name')->all())
                ->formatStateUsing(fn (string $state): string => TenantRoles::tryFrom($state)?->getLabel() ?? $state)
                ->color(fn (string $state): string => TenantRoles::tryFrom($state)?->getColor() ?? 'gray'),
            IconEntry::make('is_owner')
                ->label(__('Owner'))
                ->inlineLabel()
                ->boolean()
                ->state(fn (Model $record): bool => $record->isOwner(Filament::getTenant())),
            TextEntry::make('joined_at')
                ->label(__('Joined'))
                ->inlineLabel()
                ->dateTime()
                ->state(fn (Model $record): mixed => $record->tenants->firstWhere('id', Filament::getTenant()?->getKey())?->pivot->created_at),
            TextEntry::make('last_login_at')
                ->label(__('Last login'))
                ->inlineLabel()
                ->dateTime()
                ->placeholder('-'),
            TextEntry::make('email_verified_at')
                ->label(__('Email verified'))
                ->inlineLabel()
                ->dateTime()
                ->placeholder('-'),
        ];
    }
}
