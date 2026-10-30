<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Support\UserTenantRoles;

class ChangeUserTenantRoleAction
{
    public static function make(Model $user, UserTenantRoles $roles): Action
    {
        return Action::make('changeTenantRole')
            ->label(__('Change role'))
            ->icon(Heroicon::OutlinedKey)
            ->visible(fn (Model $record): bool => ! $record->pivot->is_owner && Gate::allows('update', $user))
            ->modalHeading(fn (Model $record): string => __('Change the role in :tenant', ['tenant' => $record->name]))
            ->fillForm(fn (Model $record): array => ['role' => $roles->for($record)->first()])
            ->schema([
                Select::make('role')
                    ->label(__('Role'))
                    ->options(collect(TenantRoles::cases())
                        ->reject(fn (TenantRoles $role): bool => $role === TenantRoles::OWNER)
                        ->mapWithKeys(fn (TenantRoles $role): array => [$role->value => $role->getLabel()])
                        ->all())
                    ->required(),
            ])
            ->action(function (array $data, Model $record) use ($user, $roles): void {
                $current = TenantRoles::tryFrom((string) $roles->for($record)->first());

                app(AssignTenantRole::class)->handle($user, $record, TenantRoles::from($data['role']), $current);

                Notification::make()
                    ->title(__('Role in :tenant updated.', ['tenant' => $record->name]))
                    ->success()
                    ->send();
            });
    }
}
