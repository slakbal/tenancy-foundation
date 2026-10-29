<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\CreateAdminUser;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\EditAdminUser;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\ListAdminUsers;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages\ViewAdminUser;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Schemas\AdminUserForm;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Schemas\AdminUserInfolist;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Tables\AdminUsersTable;
use UnitEnum;

class AdminUserResource extends Resource
{
    protected static ?string $slug = 'admins';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function getModel(): string
    {
        return config('tenancy-foundation.models.admin');
    }

    public static function getModelLabel(): string
    {
        return __('Admin');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Admins');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Users');
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if ($record === null) {
            return null;
        }

        $name = collect(config('tenancy-foundation.user_name_attributes'))
            ->map(fn (string $attribute): mixed => $record->getAttribute($attribute))
            ->filter()
            ->implode(' ');

        return $name !== '' ? $name : $record->getAttribute('email');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class])->with('roles');
    }

    public static function form(Schema $schema): Schema
    {
        return AdminUserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AdminUserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminUsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminUsers::route('/'),
            'create' => CreateAdminUser::route('/create'),
            'view' => ViewAdminUser::route('/{record}'),
            'edit' => EditAdminUser::route('/{record}/edit'),
        ];
    }
}
