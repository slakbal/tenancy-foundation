<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Slakbal\TenancyFoundation\Actions\Tenant\RemoveTenantOwner;

class RemoveUserTenantOwnerAction
{
    public static function make(?Model $user = null, ?Model $tenant = null): Action
    {
        return Action::make('removeOwner')
            ->label(__('Remove owner'))
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->color('danger')
            ->visible(fn (Model $record): bool => $record->pivot->is_owner
                && Gate::allows('update', $user ?? $record)
                && ($tenant ?? $record)->owners()->count() > 1)
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => __('Remove :name as owner of :tenant?', ['name' => ($user ?? $record)->name, 'tenant' => ($tenant ?? $record)->name]))
            ->modalDescription(__('The user stays a member of the tenant as an admin.'))
            ->action(function (Model $record) use ($user, $tenant): void {
                app(RemoveTenantOwner::class)->handle($tenant ?? $record, $user ?? $record);

                Notification::make()->title(__(':name is no longer an owner of :tenant.', ['name' => ($user ?? $record)->name, 'tenant' => ($tenant ?? $record)->name]))->success()->send();
            });
    }
}
