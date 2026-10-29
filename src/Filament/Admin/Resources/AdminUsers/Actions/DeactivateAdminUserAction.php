<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Admin\ChangeAdminUserStatus;
use Slakbal\TenancyFoundation\Enums\AdminStatus;

class DeactivateAdminUserAction
{
    public static function make(): Action
    {
        return Action::make('deactivate')
            ->label(__('Deactivate'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('warning')
            ->visible(fn (Model $record): bool => $record->status === AdminStatus::ACTIVE && ! $record->trashed())
            ->authorize('deactivate')
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => __('Deactivate :email?', ['email' => $record->email]))
            ->modalDescription(__('The user is locked out of the admin panel until reactivated.'))
            ->action(function (Model $record): void {
                app(ChangeAdminUserStatus::class)->handle($record, AdminStatus::INACTIVE);

                Notification::make()->title(__(':email has been deactivated.', ['email' => $record->email]))->success()->send();
            });
    }
}
