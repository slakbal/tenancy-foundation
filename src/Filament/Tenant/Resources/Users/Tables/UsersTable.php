<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Tables;

use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
use Slakbal\TenancyFoundation\Filament\Components\Resources\Users\UsersTable as BaseUsersTable;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\WrappedBadgeColumn;

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
}
