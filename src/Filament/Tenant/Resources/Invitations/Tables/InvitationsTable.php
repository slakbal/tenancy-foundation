<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Invitations\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\Authorisation\InvitationPermissions;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\EmailColumn;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\TimestampColumns;

class InvitationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'creator' => fn ($query) => $query->withoutGlobalScopes()->select(['id', ...config('tenancy-foundation.user_name_attributes')]),
            ]))
            ->columns(static::columns())
            ->defaultSort('created_at', 'desc')
            ->recordActions([ActionGroup::make(static::rowActions())])
            ->description(__('Pending invitations expire after :days days.', ['days' => config('tenancy-foundation.invitations.expiry_days')]));
    }

    /**
     * @return array<int, Column>
     */
    protected static function columns(): array
    {
        return [
            EmailColumn::make('email'),
            TextColumn::make('status')
                ->label(__('Status'))
                ->state(fn (Model $record): string => $record->accepted_at ? __('Accepted') : __('Pending'))
                ->badge()
                ->color(fn (Model $record): string => $record->accepted_at ? 'success' : 'warning'),
            TextColumn::make('creator.name')
                ->label(__('Invited by'))
                ->state(fn (Model $record): string => $record->creator
                    ? collect(config('tenancy-foundation.user_name_attributes'))
                        ->map(fn (string $attribute): mixed => $record->creator->getAttribute($attribute))
                        ->filter()
                        ->implode(' ')
                    : __('Support team'))
                ->color(fn (Model $record): ?string => $record->creator ? null : 'gray'),
            TextColumn::make('expires_at')
                ->label(__('Expires'))
                ->dateTime()
                ->sortable(),
            TextColumn::make('accepted_at')
                ->label(__('Accepted'))
                ->dateTime()
                ->sortable(),
            ...TimestampColumns::make(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected static function rowActions(): array
    {
        return [
            Action::make('revoke')
                ->label(__('Revoke'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->authorize(InvitationPermissions::INVITATION_DELETE)
                ->visible(fn (Model $record): bool => is_null($record->accepted_at))
                ->requiresConfirmation()
                ->action(function (Model $record): void {
                    $record->delete();

                    Notification::make()
                        ->title(__('Invitation revoked'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
