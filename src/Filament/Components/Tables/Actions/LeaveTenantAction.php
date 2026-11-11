<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Tenant\RemoveTenantMember;
use Slakbal\TenancyFoundation\Support\TenantMembershipGuard;

/**
 * Lets the signed in member leave the current tenant, through the same action that removes a member. Owners cannot
 * leave: they hand over or give up the ownership first.
 */
class LeaveTenantAction
{
    public static function make(): Action
    {
        return Action::make('leaveTenant')
            ->label(__('Leave tenant'))
            ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
            ->color('danger')
            ->visible(fn (Model $record): bool => static::canLeave($record))
            ->requiresConfirmation()
            ->modalHeading(__('Leave this tenant?'))
            ->modalDescription(fn (Model $record): string => TenantMembershipGuard::tenantCount($record) < 2
                ? __('You are leaving this tenant. It is your only tenant, so your account is deleted. An admin can restore it later.')
                : __('You are leaving this tenant and lose access to it. You keep your other tenants.'))
            ->modalSubmitActionLabel(__('Leave tenant'))
            ->successRedirectUrl(fn (): string => url('/'))
            ->action(function (Model $record): void {
                if (! static::canLeave($record)) {
                    return;
                }

                app(RemoveTenantMember::class)->handle(Filament::getTenant(), $record, $record);

                Notification::make()->title(__('You left :tenant.', ['tenant' => Filament::getTenant()->getFilamentName()]))->success()->send();
            });
    }

    protected static function canLeave(Model $record): bool
    {
        $tenant = Filament::getTenant();
        $authenticated = Filament::auth()->user();

        return $tenant
            && $authenticated
            && ! $record->trashed()
            && $authenticated->is($record)
            && ! $record->isOwner($tenant)
            && $authenticated->can('leave', $record);
    }
}
