<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Tenant\AddTenantOwner;

class MakeTenantOwnerAction
{
    public static function base(?string $name = 'makeOwner'): Action
    {
        return Action::make($name)
            ->label(__('Make owner'))
            ->icon('heroicon-o-shield-check')
            ->color('warning')
            ->modalHeading(__('Make this user the owner?'));
    }

    public static function make(?string $name = 'makeOwner'): Action
    {
        return static::base($name)
            ->modalDescription(__('This user becomes an owner of this tenant, next to the existing owners.'))
            ->requiresConfirmation()
            ->visible(fn (Model $record): bool => static::canMakeOwner($record))
            ->action(function (Model $record): void {
                if (! static::canMakeOwner($record)) {
                    return;
                }

                app(AddTenantOwner::class)->handle(Filament::getTenant(), $record, Filament::auth()->user());
                unset(Filament::getTenant()->owners_count);

                Notification::make()
                    ->title(__(':name is now an owner of this tenant.', ['name' => $record->name]))
                    ->success()
                    ->send();
            });
    }

    protected static function canMakeOwner(Model $record): bool
    {
        $tenant = Filament::getTenant();
        $authenticated = Filament::auth()->user();

        return $tenant
            && $authenticated
            && ! $record->trashed()
            && $authenticated->isOwner($tenant)
            && ! $record->isOwner($tenant);
    }
}
