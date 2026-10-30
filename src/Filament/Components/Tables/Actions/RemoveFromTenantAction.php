<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Tenant\RemoveTenantMember;
use Slakbal\TenancyFoundation\Support\TenantMembershipGuard;

class RemoveFromTenantAction
{
    public static function make(): Action
    {
        return Action::make('removeFromTenant')
            ->label(__('Remove from tenant'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->visible(fn (Model $record): bool => static::canRemove($record))
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => __('Remove :name from this tenant?', ['name' => $record->name]))
            ->modalDescription(__('The user loses access to this tenant and keeps their other tenants.'))
            ->action(function (Model $record): void {
                if (! static::canRemove($record)) {
                    return;
                }

                app(RemoveTenantMember::class)->handle(Filament::getTenant(), $record);

                Notification::make()->title(__(':name was removed from this tenant.', ['name' => $record->name]))->success()->send();
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
            && ! $record->isOwner($tenant)
            && TenantMembershipGuard::tenantCount($record) > 1;
    }
}
