<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users;

use Slakbal\TenancyFoundation\Filament\Components\Resources\Users\UserResource as BaseUserResource;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\CreateUser;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\EditUser;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\ListUsers;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Schemas\UserForm;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Tables\UsersTable;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class UserResource extends BaseUserResource
{
    // Explicitly set a distinct slug for Admin perspective on users
    protected static ?string $slug = 'users';

    protected static ?string $tenantOwnershipRelationshipName = 'tenants';

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
