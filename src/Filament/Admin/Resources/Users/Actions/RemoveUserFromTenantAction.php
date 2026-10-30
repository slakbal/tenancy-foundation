<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Slakbal\TenancyFoundation\Actions\Tenant\RemoveTenantMember;

class RemoveUserFromTenantAction
{
    public static function make(?Model $user = null, ?Model $tenant = null): Action
    {
        return Action::make('removeFromTenant')
            ->label(__('Remove from tenant'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->visible(fn (Model $record): bool => ! $record->pivot->is_owner
                && static::tenantCount($user ?? $record) > 1
                && Gate::allows('update', $user ?? $record))
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => __('Remove :name from :tenant?', ['name' => ($user ?? $record)->name, 'tenant' => ($tenant ?? $record)->name]))
            ->modalDescription(__('The user loses access to this tenant and keeps their other tenants.'))
            ->action(function (Model $record) use ($user, $tenant): void {
                app(RemoveTenantMember::class)->handle($tenant ?? $record, $user ?? $record);
                $subject = $user ?? $record;
                unset($subject->tenants_count);

                Notification::make()->title(__(':name was removed from :tenant.', ['name' => ($user ?? $record)->name, 'tenant' => ($tenant ?? $record)->name]))->success()->send();
            });
    }

    protected static function tenantCount(Model $user): int
    {
        return $user->tenants_count ??= $user->tenants()->count();
    }
}
