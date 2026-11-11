<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Tenant\TransferTenantOwnership;
use Slakbal\TenancyFoundation\Enums\MemberStatus;
use Slakbal\TenancyFoundation\Support\UserDisplayName;

/**
 * Hands the ownership over: every current owner becomes an admin and the chosen member the only owner.
 */
class TransferOwnershipAction
{
    public static function make(): Action
    {
        return Action::make('transferOwnership')
            ->label(__('Transfer ownership'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('warning')
            ->visible(fn (Model $record): bool => static::canTransfer($record))
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => __('Transfer the ownership to :name?', ['name' => UserDisplayName::for($record)]))
            ->modalDescription(__('This user becomes the only owner. You and any other owner become admins.'))
            ->action(function (Model $record): void {
                if (! static::canTransfer($record)) {
                    return;
                }

                app(TransferTenantOwnership::class)->handle(Filament::getTenant(), $record, Filament::auth()->user());
                unset(Filament::getTenant()->owners_count);

                Notification::make()
                    ->title(__('The ownership was transferred to :name.', ['name' => UserDisplayName::for($record)]))
                    ->success()
                    ->send();
            });
    }

    protected static function canTransfer(Model $record): bool
    {
        $tenant = Filament::getTenant();
        $authenticated = Filament::auth()->user();

        return $tenant
            && $authenticated
            && ! $record->trashed()
            && $authenticated->isOwner($tenant)
            && ! $record->isOwner($tenant)
            && $record->memberStatus($tenant) === MemberStatus::ACTIVE;
    }
}
