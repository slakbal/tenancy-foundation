<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class MarkUserEmailVerifiedAction
{
    public static function make(): Action
    {
        return Action::make('markEmailVerified')
            ->label(__('Mark email verified'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->authorize('markEmailVerified')
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => __('Mark :email as verified?', ['email' => $record->email]))
            ->action(function (Model $record): void {
                $record->forceFill(['email_verified_at' => now()])->save();

                Notification::make()->title(__('Email of :email marked as verified.', ['email' => $record->email]))->success()->send();
            });
    }
}
