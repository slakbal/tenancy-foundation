<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function sideComponents(): array
    {
        return [
            UserInfolist::statusSection(),
        ];
    }

    public static function detailsSection(): Section
    {
        return Section::make(__('User details'))
            ->description(__('The name and sign in email of the user.'))
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
                ->validationMessages(['unique' => fn (mixed $state): string => static::emailTakenMessage($state)])
                ->columnSpanFull(),
        ];
    }

    protected static function emailTakenMessage(mixed $email): string
    {
        $userModel = config('tenancy-foundation.models.user');

        return $userModel::onlyTrashed()->where('email', $email)->exists()
            ? __('This email belongs to a deleted user. Restore that user instead.')
            : __('validation.unique', ['attribute' => __('email')]);
    }
}
