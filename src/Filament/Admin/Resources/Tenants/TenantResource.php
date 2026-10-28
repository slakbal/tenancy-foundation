<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\EditTenant;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\ListTenants;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\ViewTenant;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\RelationManagers\UsersRelationManager;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Schemas\TenantForm;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Schemas\TenantInfolist;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Tables\TenantsTable;
use UnitEnum;

class TenantResource extends Resource
{
    protected static ?string $slug = 'tenants';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function getModel(): string
    {
        return config('tenancy-foundation.models.tenant');
    }

    public static function getModelLabel(): string
    {
        return __('Tenant');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Tenants');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Tenants');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return TenantForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TenantInfolist::configure($schema);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->with('owners');
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function table(Table $table): Table
    {
        return TenantsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'view' => ViewTenant::route('/{record}'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }
}
