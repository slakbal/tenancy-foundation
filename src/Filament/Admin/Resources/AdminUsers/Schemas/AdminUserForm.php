<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Filament\Components\Schemas\UserNameSchema;
use Slakbal\TenancyFoundation\Support\AdminAccountGuard;

class AdminUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make(static::mainComponents())->columnSpan(['lg' => 2]),
                Group::make(static::sideComponents())->columnSpan(['lg' => 1])->visibleOn('edit'),
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
            AdminUserInfolist::statusSection(),
        ];
    }

    public static function detailsSection(): Section
    {
        return Section::make(__('Admin details'))
            ->description(__('The name and sign in email of the admin.'))
            ->icon(Heroicon::OutlinedUser)
            ->schema(static::detailsFields())
            ->columns(2);
    }

    /**
     * @return array<int, Component>
     */
    protected static function detailsFields(): array
    {
        return [
            ...UserNameSchema::components(),
            TextInput::make('email')
                ->label(__('Email'))
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->columnSpanFull(),
        ];
    }

    public static function accessSection(): Section
    {
        return Section::make(__('Access'))
            ->description(__('The role decides what the admin can do.'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->schema(static::accessFields())
            ->columns(2);
    }

    /**
     * @return array<int, Component>
     */
    protected static function accessFields(): array
    {
        return [
            Select::make('role')
                ->label(__('Role'))
                ->options(fn (): array => static::assignableRoles())
                ->required()
                ->afterStateHydrated(fn (Select $component, ?Model $record) => $component->state($record?->roles->first()?->name))
                ->disabled(fn (?Model $record): bool => static::isLocked($record))
                ->helperText(fn (?Model $record): ?string => static::isLocked($record) ? static::lockedMessage() : null),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function assignableRoles(): array
    {
        $actorIsSuperAdmin = AdminAccountGuard::isSuperAdmin(Filament::auth()->user());

        return collect(AdminRoles::cases())
            ->reject(fn (AdminRoles $role): bool => $role === AdminRoles::SUPER_ADMIN && ! $actorIsSuperAdmin)
            ->mapWithKeys(fn (AdminRoles $role): array => [$role->value => $role->getLabel()])
            ->all();
    }

    protected static function isLocked(?Model $record): bool
    {
        return $record !== null && AdminAccountGuard::isLockedFor(Filament::auth()->user(), $record);
    }

    protected static function lockedMessage(): string
    {
        return __('You cannot change your own role, nor the role of the last active super admin.');
    }
}
