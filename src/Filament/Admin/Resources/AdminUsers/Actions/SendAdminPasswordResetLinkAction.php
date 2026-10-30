<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Auth\SendPasswordResetLink;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;

class SendAdminPasswordResetLinkAction
{
    public static function make(): Action
    {
        return Action::make('sendPasswordResetLink')
            ->label(__('Send password reset link'))
            ->icon(Heroicon::OutlinedKey)
            ->color('info')
            ->visible(fn (Model $record): bool => $record->status === AdminStatus::ACTIVE && ! $record->trashed() && ! Filament::auth()->user()?->is($record))
            ->authorize('sendPasswordResetLink')
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => __('Send a password reset link to :email?', ['email' => $record->email]))
            ->modalDescription(__('Earlier reset links stop working. The current password stays valid until it is reset.'))
            ->action(function (Model $record): void {
                app(SendPasswordResetLink::class)->handle($record, FilamentPanel::ADMIN);

                Notification::make()->title(__('Password reset link sent to :email.', ['email' => $record->email]))->success()->send();
            });
    }
}
