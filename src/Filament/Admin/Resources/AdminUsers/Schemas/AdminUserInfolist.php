<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;

class AdminUserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make(static::mainComponents())->columnSpan(['lg' => 2]),
                Group::make(static::sideComponents())->columnSpan(['lg' => 1]),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    protected static function mainComponents(): array
    {
        return [
            static::detailsSection(),
            static::accessSection(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function sideComponents(): array
    {
        return [
            static::statusSection(),
        ];
    }

    public static function detailsSection(): Section
    {
        return Section::make(__('Admin details'))
            ->icon(Heroicon::OutlinedUser)
            ->schema(static::detailsEntries())
            ->columns(2);
    }

    /**
     * @return array<int, Component>
     */
    protected static function detailsEntries(): array
    {
        return [
            ...collect(config('tenancy-foundation.user_name_attributes'))
                ->map(fn (string $attribute): Component => TextEntry::make($attribute)
                    ->label(__(Str::of($attribute)->replace('_', ' ')->ucfirst()->toString())))
                ->all(),
            TextEntry::make('email')
                ->label(__('Email'))
                ->copyable()
                ->columnSpanFull(),
        ];
    }

    public static function accessSection(): Section
    {
        return Section::make(__('Access'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->schema(static::accessEntries())
            ->columns(2);
    }

    /**
     * @return array<int, Component>
     */
    protected static function accessEntries(): array
    {
        return [
            TextEntry::make('roles.name')
                ->label(__('Role'))
                ->badge()
                ->formatStateUsing(fn (string $state): string => AdminRoles::tryFrom($state)?->getLabel() ?? Str::headline($state))
                ->color(fn (string $state): string => AdminRoles::tryFrom($state)?->getColor() ?? 'gray')
                ->icon(fn (string $state): ?string => AdminRoles::tryFrom($state)?->getIcon())
                ->placeholder('-'),
            TextEntry::make('status')
                ->label(__('Status'))
                ->badge(),
        ];
    }

    public static function statusSection(): Section
    {
        return Section::make(__('Status'))
            ->icon(Heroicon::OutlinedSignal)
            ->compact()
            ->schema(static::statusEntries());
    }

    /**
     * @return array<int, Component>
     */
    protected static function statusEntries(): array
    {
        return [
            TextEntry::make('status')
                ->label(__('Status'))
                ->inlineLabel()
                ->badge(),
            TextEntry::make('last_login_at')
                ->label(__('Last login'))
                ->inlineLabel()
                ->dateTime()
                ->placeholder('-'),
            TextEntry::make('last_login_ip')
                ->label(__('Last login IP'))
                ->inlineLabel()
                ->placeholder('-'),
            TextEntry::make('email_verified_at')
                ->label(__('Email verified'))
                ->inlineLabel()
                ->dateTime()
                ->placeholder('-'),
            TextEntry::make('created_at')
                ->label(__('Created'))
                ->inlineLabel()
                ->dateTime(),
        ];
    }
}
