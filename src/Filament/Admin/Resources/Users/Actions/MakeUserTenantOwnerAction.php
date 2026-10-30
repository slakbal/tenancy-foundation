<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Slakbal\TenancyFoundation\Actions\Tenant\TransferTenantOwnership;

class MakeUserTenantOwnerAction
{
    public static function make(Model $user): Action
    {
        return Action::make('makeOwner')
            ->label(__('Make owner'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('warning')
            ->visible(fn (Model $record): bool => ! $record->pivot->is_owner && Gate::allows('update', $user))
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => __('Make this user the owner of :tenant?', ['tenant' => $record->name]))
            ->modalDescription(__('Ownership of this tenant is transferred to this user. The previous owner stays a member.'))
            ->action(function (Model $record) use ($user): void {
                app(TransferTenantOwnership::class)->handleAsAdministrator($record, $user);

                Notification::make()
                    ->title(__(':name is now the owner of :tenant.', ['name' => $user->name, 'tenant' => $record->name]))
                    ->success()
                    ->send();
            });
    }
}
