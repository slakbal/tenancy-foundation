<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Tables;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
use Slakbal\TenancyFoundation\Filament\Components\Resources\Users\UsersTable as BaseUsersTable;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\ImpersonateUserAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\WrappedBadgeColumn;

class UsersTable extends BaseUsersTable
{
    protected static function modifyQuery(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->withSoleOwnerFlag()->with([
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
            WrappedBadgeColumn::make('ownedTenants.name')->label(__('Owner of')),
            WrappedBadgeColumn::make('memberTenants.name')->label(__('Member of')),
        ]);

        return $columns;
    }

    /**
     * @return array<int, Action>
     */
    protected static function rowActions(): array
    {
        return [
            ImpersonateUserAction::make(), // see admin model for authorization method: canImpersonate
            ViewAction::make(),
            ...parent::rowActions(),
        ];
    }
}
