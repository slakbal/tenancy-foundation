<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Tables;

use Filament\Actions\Action;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Slakbal\TenancyFoundation\Filament\Components\Resources\Users\UsersTable as BaseUsersTable;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\MakeTenantOwnerAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\RemoveFromTenantAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\RemoveTenantOwnerAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\RoleBadgeColumn;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\WrappedBadgeColumn;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Filters\RoleFilter;

class UsersTable extends BaseUsersTable
{
    protected static function modifyQuery(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->withSoleOwnerFlag()->with([
            'roles',
            'tenants',
            'ownedTenants',
            'memberTenants',
        ]));
    }

    /**
     * @return array<int, Column>
     */
    protected static function columns(): array
    {
        $columns = parent::columns();

        array_splice($columns, 2, 0, [
            RoleBadgeColumn::make(),
            WrappedBadgeColumn::make('ownedTenants.name')->label(__('Owner of')),
            WrappedBadgeColumn::make('memberTenants.name')->label(__('Member of')),
        ]);

        return $columns;
    }

    /**
     * @return array<int, BaseFilter>
     */
    protected static function filters(): array
    {
        return [
            RoleFilter::make(),
            ...parent::filters(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected static function rowActions(): array
    {
        return [
            MakeTenantOwnerAction::make(),
            RemoveTenantOwnerAction::make(),
            RemoveFromTenantAction::make(),
            ...parent::rowActions(),
        ];
    }
}
