<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\CancelTenant;

class CancelTenantAction
{
    public static function make(): Action
    {
        return Action::make('cancel')
            ->label(__('Cancel tenant'))
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
            });
    }
}
