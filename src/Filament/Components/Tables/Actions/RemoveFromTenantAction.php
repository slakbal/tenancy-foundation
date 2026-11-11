<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Tenant\RemoveTenantMember;
use Slakbal\TenancyFoundation\Support\TenantMembershipGuard;
use Slakbal\TenancyFoundation\Support\UserDisplayName;

/**
 * Removes a member from the current tenant. A member of other tenants is detached; a member whose only tenant this is
 * has their account deleted (soft), and the label and confirmation say which of the two will happen.
 */
class RemoveFromTenantAction
{
    public static function make(): Action
    {
        return Action::make('removeFromTenant')
            ->label(fn (Model $record): string => static::deletesAccount($record) ? __('Delete user') : __('Remove from tenant'))
            ->icon(fn (Model $record): Heroicon => static::deletesAccount($record) ? Heroicon::OutlinedTrash : Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->visible(fn (Model $record): bool => static::canRemove($record))
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => static::deletesAccount($record)
                ? __('Delete :name?', ['name' => UserDisplayName::for($record)])
                : __('Remove :name from this tenant?', ['name' => UserDisplayName::for($record)]))
            ->modalDescription(fn (Model $record): string => static::deletesAccount($record)
                ? __('This is the only tenant of the user, so their account is deleted. An admin can restore it later.')
                : __('The user loses access to this tenant and keeps their other tenants.'))
            ->action(function (Model $record): void {
                if (! static::canRemove($record)) {
                    return;
                }

                $deleted = app(RemoveTenantMember::class)->handle(Filament::getTenant(), $record, Filament::auth()->user());

                Notification::make()
                    ->title($deleted
                        ? __(':name was deleted.', ['name' => UserDisplayName::for($record)])
                        : __(':name was removed from this tenant.', ['name' => UserDisplayName::for($record)]))
                    ->success()
                    ->send();
            });
    }

    protected static function canRemove(Model $record): bool
    {
        $tenant = Filament::getTenant();
        $authenticated = Filament::auth()->user();

        return $tenant
            && $authenticated
            && ! $record->trashed()
            && ! $authenticated->is($record)
            && $authenticated->can('removeMember', $record)
            && ! $record->isOwner($tenant);
    }

    protected static function deletesAccount(Model $record): bool
    {
        return TenantMembershipGuard::tenantCount($record) < 2;
    }
}
