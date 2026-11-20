<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Slakbal\TenancyFoundation\Enums\DomainRuleType;

class DomainRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('value')
            ->modelLabel(__('Access rule'))
            ->pluralModelLabel(__('Access rules'))
            ->defaultSort('value')
            ->columns([
                TextColumn::make('value')
                    ->label(__('Domain or email address'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(DomainRuleType::class),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
