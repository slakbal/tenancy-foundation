<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Actions;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;
use STS\FilamentImpersonate\Actions\Impersonate;

class ImpersonateUserAction
{
    public static function make(?string $name = null): Impersonate
    {
        return Impersonate::make(name: $name)
            ->before(function (Model $record) {
                $panel = Filament::getPanel(FilamentPanel::TENANT->getPanelId());
                $tenant = $record->getDefaultTenant($panel);

                if (! $tenant) {
                    Notification::make()
                        ->title(__('Impersonation failed'))
                        ->body(__('No tenant could be found for ":name".', ['name' => $record->name]))
                        ->danger()
                        ->send();

                    // Halts the Livewire action before session/auth state changes occur
                    throw new Halt('Impersonation Failed');
                }
            })
            ->redirectTo(function (Model $record) {
                $panel = Filament::getPanel(FilamentPanel::TENANT->getPanelId());
                $tenant = $record->getDefaultTenant($panel);

                return route("filament.{$panel->getId()}.pages.dashboard", [
                    'tenant' => $tenant->subdomain,
                ]);
            })
            ->backTo(UserResource::getUrl('index'))
            ->guard(guard: FilamentPanel::TENANT->getAuthGuard())
            ->iconButton()
            ->tooltip(fn (Impersonate $action): string => $action->getLabel())
            ->color('warning')
            ->requiresConfirmation();
    }
}
