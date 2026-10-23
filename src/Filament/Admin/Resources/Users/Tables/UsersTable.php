<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Tables;

use Slakbal\TenancyFoundation\Filament\Components\Resources\Users\UsersTable as BaseUsersTable;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\ImpersonateUserAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\WrappedBadgeColumn;
use Filament\Actions\Action;
use Filament\Tables\Columns\Column;
use Filament\Tables\Table;

class UsersTable extends BaseUsersTable
{
    protected static function modifyQuery(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with([
            'tenants',
        ]));
    }

    /**
     * @return array<int, Column>
     */
    protected static function columns(): array
    {
        $columns = parent::columns();

        array_splice($columns, 2, 0, [
            WrappedBadgeColumn::make('tenants.name')->label(__('Tenants')),
        ]);

        return $columns;
    }

    /**
     * @return array<int, Action>
     */
    protected static function recordActions(): array
    {
        return [
            ImpersonateUserAction::make(), // see admin model for authorization method: canImpersonate
            ...parent::recordActions(),
        ];
    }
}
