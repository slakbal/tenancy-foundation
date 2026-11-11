<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Tenant\ChangeTenantMemberStatus;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantUserAbilities;
use Slakbal\TenancyFoundation\Enums\MemberStatus;

class ChangeMemberStatusAction
{
    public static function deactivate(): Action
    {
        return static::base(TenantUserAbilities::Deactivate, MemberStatus::INACTIVE)
            ->label(__('Deactivate'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('warning')
            ->modalHeading(fn (Model $record): string => __('Deactivate :name?', ['name' => $record->name]))
            ->modalDescription(__('The user is locked out of this tenant until reactivated. Their other tenants are not affected.'));
    }

    public static function activate(): Action
    {
        return static::base(TenantUserAbilities::Activate, MemberStatus::ACTIVE)
            ->label(__('Activate'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalHeading(fn (Model $record): string => __('Activate :name?', ['name' => $record->name]))
            ->modalDescription(__('The user can use this tenant again.'));
    }

    protected static function base(TenantUserAbilities $ability, MemberStatus $status): Action
    {
        return Action::make($ability->value)
            ->visible(fn (Model $record): bool => static::canChange($record, $ability, $status))
            ->requiresConfirmation()
            ->action(function (Model $record) use ($ability, $status): void {
                if (! static::canChange($record, $ability, $status)) {
                    return;
                }

                app(ChangeTenantMemberStatus::class)->handle(Filament::getTenant(), $record, $status, Filament::auth()->user());

                Notification::make()
                    ->title($status === MemberStatus::ACTIVE
                        ? __(':name has been activated.', ['name' => $record->name])
                        : __(':name has been deactivated.', ['name' => $record->name]))
                    ->success()
                    ->send();
            });
    }

    protected static function canChange(Model $record, TenantUserAbilities $ability, MemberStatus $status): bool
    {
        $tenant = Filament::getTenant();
        $authenticated = Filament::auth()->user();

        return $tenant
            && $authenticated
            && ! $record->trashed()
            && ! $authenticated->is($record)
            && ! $record->isOwner($tenant)
            && $record->memberStatus($tenant) !== $status
            && $authenticated->can($ability, $record);
    }
}
