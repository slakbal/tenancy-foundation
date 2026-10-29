<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\SendAdminPasswordResetLink;
use Slakbal\TenancyFoundation\Enums\AdminStatus;

class SendAdminPasswordResetLinkAction
{
    public static function make(): Action
    {
        return Action::make('sendPasswordResetLink')
            ->label(__('Send password reset link'))
            ->icon(Heroicon::OutlinedKey)
            ->color('info')
            ->visible(fn (Model $record): bool => $record->status === AdminStatus::ACTIVE && ! $record->trashed())
            ->authorize('sendPasswordResetLink')
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => __('Send a password reset link to :email?', ['email' => $record->email]))
            ->modalDescription(__('Earlier reset links stop working. The current password stays valid until it is reset.'))
            ->action(function (Model $record): void {
                app(SendAdminPasswordResetLink::class)->handle($record);

                Notification::make()->title(__('Password reset link sent to :email.', ['email' => $record->email]))->success()->send();
            });
    }
}
