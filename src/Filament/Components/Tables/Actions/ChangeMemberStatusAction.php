<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Tenant\ChangeTenantMemberStatus;
use Slakbal\TenancyFoundation\Enums\MemberStatus;

class ChangeMemberStatusAction
{
    public static function deactivate(): Action
    {
        return static::base('deactivate', MemberStatus::INACTIVE)
            ->label(__('Deactivate'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('warning')
            ->modalHeading(fn (Model $record): string => __('Deactivate :name?', ['name' => $record->name]))
            ->modalDescription(__('The user is locked out of this tenant until reactivated. Their other tenants are not affected.'));
    }

    public static function activate(): Action
    {
        return static::base('activate', MemberStatus::ACTIVE)
            ->label(__('Activate'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalHeading(fn (Model $record): string => __('Activate :name?', ['name' => $record->name]))
            ->modalDescription(__('The user can use this tenant again.'));
    }

    protected static function base(string $name, MemberStatus $status): Action
    {
        return Action::make($name)
            ->visible(fn (Model $record): bool => static::canChange($record, $name, $status))
            ->requiresConfirmation()
            ->action(function (Model $record) use ($name, $status): void {
                if (! static::canChange($record, $name, $status)) {
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

    protected static function canChange(Model $record, string $ability, MemberStatus $status): bool
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
