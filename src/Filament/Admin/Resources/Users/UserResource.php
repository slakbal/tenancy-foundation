<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users;

use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\CreateUser;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\EditUser;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Pages\ListUsers;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Schemas\UserForm;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Tables\UsersTable;
use Slakbal\TenancyFoundation\Filament\Components\Resources\Users\UserResource as BaseUserResource;

class UserResource extends BaseUserResource
{
    // Explicitly set a distinct slug for Admin perspective on users
    protected static ?string $slug = 'all-users';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
