<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Enums\MemberStatus;
use Slakbal\TenancyFoundation\Filament\Components\Resources\Users\UserForm as BaseUserForm;
use Slakbal\TenancyFoundation\Filament\Components\Schemas\UserNameSchema;

class UserForm extends BaseUserForm
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
            static::statusSection(),
        ];
    }

    public static function detailsSection(): Section
    {
        return Section::make(__('User details'))
            ->description(__('The name of the user. The sign in email is changed by the user on their own profile.'))
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
            ...collect(UserNameSchema::components())
                ->each(fn (TextInput $field): TextInput => $field
                    ->disabled(fn (?Model $record): bool => static::belongsToOtherTenants($record))
                    ->helperText(fn (?Model $record): ?string => static::belongsToOtherTenants($record)
                        ? __('This user belongs to other tenants, so only they can change their name.')
                        : null))
                ->all(),
            TextInput::make('email')
                ->label(__('Email'))
                ->disabled()
                ->dehydrated(false)
                ->columnSpanFull(),
        ];
    }

    public static function accessSection(): Section
    {
        return Section::make(__('Access'))
            ->description(__('The roles decide what the user can do in this tenant.'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->schema(static::accessFields())
            ->visible(fn (?Model $record): bool => static::canChangeRoles($record));
    }

    /**
     * @return array<int, Component>
     */
    protected static function accessFields(): array
    {
        return [
            Select::make('tenant_roles')
                ->label(__('Roles'))
                ->multiple()
                ->options(TenantRoles::assignableOptions())
                ->required(),
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

    protected static function canChangeRoles(?Model $record): bool
    {
        if ($record === null) {
            return false;
        }

        return ! $record->isOwner(Filament::getTenant()) && ! Filament::auth()->user()?->is($record);
    }

    protected static function belongsToOtherTenants(?Model $record): bool
    {
        return $record !== null && $record->tenants->count() > 1;
    }
}
