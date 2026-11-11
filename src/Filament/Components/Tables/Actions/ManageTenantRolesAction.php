<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Slakbal\TenancyFoundation\Actions\Roles\SyncTenantRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Support\UserTenantRoles;

/**
 * Sets the tenant roles of a user in a tenant. The admin panel passes the user or the tenant (the other is the table
 * record) and relies on the default visibility; the tenant panel passes its own visibility closure. The owner role is
 * never offered: ownership has its own actions.
 */
class ManageTenantRolesAction
{
    /**
     * @param  (Closure(Model): bool)|null  $visible  Replaces the default visibility (not an owner row and the update ability).
     */
    public static function make(?Model $user = null, ?Model $tenant = null, string $name = 'manageTenantRoles', ?Closure $visible = null): Action
    {
        return Action::make($name)
            ->label(__('Manage roles'))
            ->icon(Heroicon::OutlinedKey)
            ->visible($visible ?? fn (Model $record): bool => ! $record->pivot->is_owner && Gate::allows('update', $user ?? $record))
            ->modalHeading(fn (Model $record): string => __('Roles of :name in :tenant', ['name' => ($user ?? $record)->name, 'tenant' => ($tenant ?? $record)->name]))
            ->fillForm(fn (Model $record): array => ['roles' => (new UserTenantRoles($user ?? $record))->for($tenant ?? $record)
                ->reject(fn (string $role): bool => $role === TenantRoles::OWNER->value)->values()->all()])
            ->schema([
                Select::make('roles')
                    ->multiple()
                    ->label(__('Roles'))
                    ->options(TenantRoles::assignableOptions())
                    ->required(),
            ])
            ->action(function (array $data, Model $record) use ($user, $tenant): void {
                app(SyncTenantRoles::class)->handle($user ?? $record, $tenant ?? $record, array_map(TenantRoles::from(...), $data['roles']));

                Notification::make()->title(__('Roles updated.'))->success()->send();
            });
    }
}
