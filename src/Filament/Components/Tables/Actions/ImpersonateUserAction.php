<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Actions;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Impersonation\IssueImpersonationHandoff;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Enums\TenantResolution;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;
use STS\FilamentImpersonate\Actions\Impersonate;

class ImpersonateUserAction
{
    public static function make(?string $name = null): Impersonate
    {
        $action = Impersonate::make(name: $name)
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
                    'tenant' => $tenant->getAttribute(TenantResolution::current()->attribute()),
                ]);
            })
            ->backTo(fn (): string => request()->header('referer') ?: UserResource::getUrl('index'))
            ->guard(guard: FilamentPanel::TENANT->getAuthGuard())
            ->color('warning')
            ->requiresConfirmation();

        if (TenantResolution::current() === TenantResolution::DOMAIN) {
            // Host-only cookies keep the backoffice session off the tenant host: the admin stays signed in here and opens a one-time link there.
            $action->action(function (Model $record) use ($action): void {
                $panel = Filament::getPanel(FilamentPanel::TENANT->getPanelId());

                $action->redirect(app(IssueImpersonationHandoff::class)->handle(
                    admin: Filament::auth()->user(),
                    user: $record,
                    tenant: $record->getDefaultTenant($panel),
                    backTo: $action->getBackTo(),
                ), navigate: false);
            });
        }

        return $action;
    }
}
