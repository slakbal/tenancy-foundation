<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Auth\SendPasswordResetLink;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;

class SendUserPasswordResetLinkAction
{
    public static function make(): Action
    {
        return Action::make('sendPasswordResetLink')
            ->label(__('Send password reset link'))
            ->icon(Heroicon::OutlinedKey)
            ->color('info')
            ->authorize('sendPasswordResetLink')
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => __('Send a password reset link to :email?', ['email' => $record->email]))
            ->modalDescription(__('Earlier reset links stop working. The current password stays valid until it is reset.'))
            ->action(function (Model $record): void {
                app(SendPasswordResetLink::class)->handle($record, FilamentPanel::TENANT);

                Notification::make()->title(__('Password reset link sent to :email.', ['email' => $record->email]))->success()->send();
            });
    }
}
