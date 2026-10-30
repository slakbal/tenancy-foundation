<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Resources\Users;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\EmailColumn;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\LastLoginColumns;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\NameColumn;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\TimestampColumns;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Filters\VerifiedFilter;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return static::modifyQuery($table)
            ->columns(static::columns())
            ->filters(static::filters())
            ->recordActions(static::recordActions())
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                    ForceDeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<int, BaseFilter>
     */
    protected static function filters(): array
    {
        return [
            VerifiedFilter::make(),
            TrashedFilter::make(),
        ];
    }

    protected static function modifyQuery(Table $table): Table
    {
        return $table;
    }

    /**
     * @return array<int, Column>
     */
    protected static function columns(): array
    {
        return [
            NameColumn::make('name'),
            EmailColumn::make('email'),
            ...LastLoginColumns::make(),
            ...TimestampColumns::make(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected static function recordActions(): array
    {
        return [ActionGroup::make(static::rowActions())];
    }

    /**
     * @return array<int, Action>
     */
    protected static function rowActions(): array
    {
        return [
            EditAction::make()->hidden(fn (Model $record): bool => $record->trashed()),
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
