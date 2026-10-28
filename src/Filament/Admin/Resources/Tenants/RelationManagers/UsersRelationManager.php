<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Tables\TenantUsersTable;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static function getTableClass(): string
    {
        return TenantUsersTable::class;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Users');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->members()->count();
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
