<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Slakbal\TenancyFoundation\Actions\Roles\SyncTenantRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Support\UserTenantRoles;

class ManageTenantRolesAction
{
    public static function make(?Model $user = null, ?Model $tenant = null): Action
    {
        return Action::make('manageTenantRoles')
            ->label(__('Manage roles'))
            ->icon(Heroicon::OutlinedKey)
            ->visible(fn (Model $record): bool => ! $record->pivot->is_owner && Gate::allows('update', $user ?? $record))
            ->modalHeading(fn (Model $record): string => __('Roles of :name in :tenant', ['name' => ($user ?? $record)->name, 'tenant' => ($tenant ?? $record)->name]))
            ->fillForm(fn (Model $record): array => ['roles' => (new UserTenantRoles($user ?? $record))->for($tenant ?? $record)->all()])
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
