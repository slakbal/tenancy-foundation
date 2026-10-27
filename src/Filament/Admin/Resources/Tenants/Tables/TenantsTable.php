<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Tables;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\CancelTenant;
use Slakbal\TenancyFoundation\Actions\ReactivateTenant;
use Slakbal\TenancyFoundation\Enums\TenantStatus;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('owners'))
            ->defaultSort('created_at', 'desc')
            ->columns(static::columns())
            ->filters(static::filters())
            ->recordActions(static::recordActions());
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
            TextColumn::make('owners.email')
                ->label(__('Owner'))
                ->wrap(),
            TextColumn::make('members_count')
                ->label(__('Members'))
                ->counts('members')
                ->sortable(),
            TextColumn::make('status')
                ->label(__('Status'))
                ->badge()
                ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('cancelled_at', $direction)),
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
            TextColumn::make('created_at')
                ->label(__('Created'))
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
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
            Action::make('cancel')
                ->label(__('Cancel tenant'))
                ->hiddenLabel()
                ->iconButton()
                ->tooltip(__('Cancel tenant'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->authorize('cancel')
                ->visible(fn (Model $record): bool => ! $record->isCancelled())
                ->requiresConfirmation()
                ->modalHeading(fn (Model $record): string => __('Cancel :tenant?', ['tenant' => $record->name]))
                ->modalDescription(__('Everyone is locked out and the owners are emailed. All data is deleted after :days days and cannot be recovered.', [
                    'days' => (int) config('tenancy-foundation.cancellation.grace_days'),
                ]))
                ->action(function (Model $record): void {
                    app(CancelTenant::class)->handle($record);

                    Notification::make()->title(__(':tenant has been cancelled.', ['tenant' => $record->name]))->success()->send();
                }),
            Action::make('reactivate')
                ->label(__('Reactivate tenant'))
                ->hiddenLabel()
                ->iconButton()
                ->tooltip(__('Reactivate tenant'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('success')
                ->authorize('reactivate')
                ->visible(fn (Model $record): bool => $record->isCancelled())
                ->requiresConfirmation()
                ->modalHeading(fn (Model $record): string => __('Reactivate :tenant?', ['tenant' => $record->name]))
                ->modalDescription(__('Everyone can sign in again and the scheduled deletion is cancelled.'))
                ->action(function (Model $record): void {
                    app(ReactivateTenant::class)->handle($record);

                    Notification::make()->title(__(':tenant has been reactivated.', ['tenant' => $record->name]))->success()->send();
                }),
        ];
    }
}
