<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Contact\ChangeContactRequestStatus;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\ContactSource;
use Slakbal\TenancyFoundation\Enums\ContactStatus;

class ContactRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('60s')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Received'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('source')
                    ->label(__('Source'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                IconColumn::make('responded_at')
                    ->label(__('Responded'))
                    ->boolean()
                    ->state(fn ($record): bool => $record->isResponded())
                    ->toggleable(),
                TextColumn::make('responded_by')
                    ->label(__('Responded by'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(ContactStatus::class)
                    ->multiple()
                    ->default([ContactStatus::NEW->value]),
                SelectFilter::make('source')
                    ->label(__('Source'))
                    ->options(ContactSource::class),
                TernaryFilter::make('responded')
                    ->label(__('Responded'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('responded_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('responded_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->label(__('Open'))->icon(Heroicon::OutlinedEye),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('dismiss')
                        ->label(__('Dismiss'))
                        ->icon(ContactStatus::DISMISSED->getIcon())
                        ->color(ContactStatus::DISMISSED->getColor())
                        ->visible(fn (): bool => (bool) Filament::auth()->user()?->can(AdminPermissions::CONTACT_REQUEST_UPDATE))
                        ->authorizeIndividualRecords('dismiss')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $records
                                ->reject(fn (Model $record): bool => ! $record->canBeDismissed())
                                ->each(fn (Model $record) => app(ChangeContactRequestStatus::class)->handle($record, ContactStatus::DISMISSED));
                        })
                        ->successNotificationTitle(__('Selected requests dismissed.'))
                        ->failureNotificationTitle(__('Requests that have been responded to cannot be dismissed.')),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
