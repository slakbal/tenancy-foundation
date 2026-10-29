<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\ChangeAdminUserStatus;
use Slakbal\TenancyFoundation\Enums\AdminStatus;

class ActivateAdminUserAction
{
    public static function make(): Action
    {
        return Action::make('activate')
            ->label(__('Activate'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Model $record): bool => $record->status === AdminStatus::INACTIVE && ! $record->trashed())
            ->authorize('activate')
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => __('Activate :email?', ['email' => $record->email]))
            ->modalDescription(__('The user can sign in to the admin panel again.'))
            ->action(function (Model $record): void {
                app(ChangeAdminUserStatus::class)->handle($record, AdminStatus::ACTIVE);

                Notification::make()->title(__(':email has been activated.', ['email' => $record->email]))->success()->send();
            });
    }
}
