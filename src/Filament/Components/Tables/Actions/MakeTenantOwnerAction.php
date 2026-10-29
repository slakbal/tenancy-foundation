<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\TransferTenantOwnership;

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
            ->modalDescription(__('Ownership of this tenant is transferred to this user. You will no longer be the owner.'))
            ->requiresConfirmation()
            ->visible(fn (Model $record): bool => static::canMakeOwner($record))
            ->action(function (Model $record): void {
                if (! static::canMakeOwner($record)) {
                    return;
                }

                app(TransferTenantOwnership::class)->handle(Filament::getTenant(), $record, Filament::auth()->user());

                Notification::make()
                    ->title(__(':name is now the owner of this tenant.', ['name' => $record->name]))
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
            && $authenticated->isOwner($tenant)
            && ! $record->isOwner($tenant);
    }
}
