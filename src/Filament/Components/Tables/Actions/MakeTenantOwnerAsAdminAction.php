<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\TransferTenantOwnership;

class MakeTenantOwnerAsAdminAction
{
    public static function make(?string $name = 'makeOwner'): Action
    {
        return MakeTenantOwnerAction::base($name)
            ->modalDescription(__('Ownership of the selected tenant is transferred to this user. The previous owner stays a member.'))
            ->schema(fn (Model $record): array => [
                Select::make('tenant_id')
                    ->label(__('Tenant'))
                    ->options(fn (): array => $record->memberTenants->mapWithKeys(fn (Model $tenant): array => [
                        $tenant->getKey() => "{$tenant->name} ({$tenant->subdomain})",
                    ])->all())
                    ->required(),
            ])
            ->visible(fn (Model $record): bool => static::canMakeOwner($record))
            ->action(function (Model $record, array $data): void {
                if (! static::canMakeOwner($record)) {
                    return;
                }

                $tenant = $record->memberTenants->firstWhere('id', $data['tenant_id']);

                if (! $tenant) {
                    return;
                }

                app(TransferTenantOwnership::class)->handleAsAdministrator($tenant, $record);

                Notification::make()
                    ->title(__(':name is now the owner of :tenant.', ['name' => $record->name, 'tenant' => $tenant->name]))
                    ->success()
                    ->send();
            });
    }

    protected static function canMakeOwner(Model $record): bool
    {
        $administrator = Filament::auth()->user();

        return $administrator
            && $administrator->can('update', $record)
            && $record->loadMissing('memberTenants')->memberTenants->isNotEmpty();
    }
}
