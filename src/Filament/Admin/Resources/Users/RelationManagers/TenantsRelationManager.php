<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Tables\UserTenantsTable;

class TenantsRelationManager extends RelationManager
{
    protected static string $relationship = 'tenants';

    protected static function getTableClass(): string
    {
        return UserTenantsTable::class;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Tenants');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->tenants()->count();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return static::getTableClass()::configure($table, $this->getOwnerRecord());
    }
}
