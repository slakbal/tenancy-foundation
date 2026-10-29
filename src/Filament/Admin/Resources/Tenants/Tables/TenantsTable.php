<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\TenantStatus;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Actions\CancelTenantAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Actions\ReactivateTenantAction;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return static::modifyQuery($table)
            ->defaultSort('created_at', 'desc')
            ->columns(static::columns())
            ->filters(static::filters())
            ->recordActions(static::recordActions());
    }

    protected static function modifyQuery(Table $table): Table
    {
        return $table;
    }

    /**
     * @return array<int, TextColumn>
     */
    protected static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('Name'))
                ->searchable()
                ->sortable(),
            TextColumn::make('subdomain')
                ->label(__('Subdomain'))
                ->searchable()
                ->sortable(),
            TextColumn::make('status')
                ->label(__('Status'))
                ->badge()
                ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('cancelled_at', $direction)),
            TextColumn::make('created_at')
                ->label(__('Created'))
                ->dateTime()
                ->sortable(),
            TextColumn::make('cancelled_at')
                ->label(__('Cancelled'))
                ->dateTime()
                ->placeholder('-')
                ->sortable(),
            TextColumn::make('scheduled_for_deletion_at')
                ->label(__('Deletion scheduled'))
                ->dateTime()
                ->description(fn (Model $record): ?string => $record->scheduled_for_deletion_at?->diffForHumans())
                ->placeholder('-')
                ->sortable(),
        ];
    }

    /**
     * @return array<int, SelectFilter|Filter>
     */
    protected static function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->label(__('Status'))
                ->options(TenantStatus::class)
                ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    TenantStatus::ACTIVE->value => $query->active(),
                    TenantStatus::CANCELLED->value => $query->whereNotNull('cancelled_at'),
                    default => $query,
                }),
            Filter::make('due_for_deletion')
                ->label(__('Due for deletion'))
                ->toggle()
                ->query(fn (Builder $query): Builder => $query->dueForDeletion()),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected static function recordActions(): array
    {
        return [
            ActionGroup::make([
                ViewAction::make(),
                EditAction::make(),
                CancelTenantAction::make(),
                ReactivateTenantAction::make(),
            ]),
        ];
    }
}
