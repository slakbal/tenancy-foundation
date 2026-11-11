<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Tenant\RemoveTenantOwner;

class RemoveTenantOwnerAction
{
    public static function make(): Action
    {
        return Action::make('removeOwner')
            ->label(__('Remove ownership'))
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->color('danger')
            ->visible(fn (Model $record): bool => static::canRemoveOwner($record))
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => __('Remove :name as owner?', ['name' => $record->name]))
            ->modalDescription(__('The user stays a member of this tenant as an admin.'))
            ->action(function (Model $record): void {
                if (! static::canRemoveOwner($record)) {
                    return;
                }

                app(RemoveTenantOwner::class)->handle(Filament::getTenant(), $record, Filament::auth()->user());
                unset(Filament::getTenant()->owners_count);

                Notification::make()->title(__(':name is no longer an owner of this tenant.', ['name' => $record->name]))->success()->send();
            });
    }

    protected static function canRemoveOwner(Model $record): bool
    {
        $tenant = Filament::getTenant();
        $authenticated = Filament::auth()->user();

        return $tenant
            && $authenticated
            && ! $record->trashed()
            && $authenticated->isOwner($tenant)
            && $record->isOwner($tenant)
            && ($tenant->owners_count ??= $tenant->owners()->count()) > 1;
    }
}
