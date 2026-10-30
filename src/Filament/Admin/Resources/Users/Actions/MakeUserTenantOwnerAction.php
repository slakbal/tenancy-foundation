<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Slakbal\TenancyFoundation\Actions\Tenant\AddTenantOwner;

class MakeUserTenantOwnerAction
{
    public static function make(?Model $user = null, ?Model $tenant = null): Action
    {
        return Action::make('makeOwner')
            ->label(__('Make owner'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('warning')
            ->visible(fn (Model $record): bool => ! $record->pivot->is_owner && Gate::allows('update', $user ?? $record))
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => __('Make :name an owner of :tenant?', ['name' => ($user ?? $record)->name, 'tenant' => ($tenant ?? $record)->name]))
            ->modalDescription(__('This user becomes an owner of the tenant, next to the existing owners.'))
            ->action(function (Model $record) use ($user, $tenant): void {
                app(AddTenantOwner::class)->handle($tenant ?? $record, $user ?? $record);

                Notification::make()->title(__(':name is now an owner of :tenant.', ['name' => ($user ?? $record)->name, 'tenant' => ($tenant ?? $record)->name]))->success()->send();
            });
    }
}
