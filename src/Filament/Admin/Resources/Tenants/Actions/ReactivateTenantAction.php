<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\ReactivateTenant;

class ReactivateTenantAction
{
    public static function make(): Action
    {
        return Action::make('reactivate')
            ->label(__('Reactivate tenant'))
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
            });
    }
}
