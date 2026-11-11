<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenantUser;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
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
                ->email()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->maxLength(255)
                ->unique(config('tenancy-foundation.models.user'), 'email', ignoreRecord: true)
                ->validationMessages(['unique' => fn (): string => CreateTenantUser::emailTakenMessage()])
                ->disabled(fn (string $operation): bool => $operation !== 'create')
                ->dehydrated(fn (string $operation): bool => $operation === 'create')
                ->columnSpanFull(),
        ];
    }

    public static function accessSection(): Section
    {
        return Section::make(__('Access'))
            ->description(__('The roles decide what the user can do in this tenant.'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->schema(static::accessFields())
            ->visible(fn (?Model $record, string $operation): bool => $operation === 'create' || static::canChangeRoles($record));
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
        return UserInfolist::statusEntries();
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
