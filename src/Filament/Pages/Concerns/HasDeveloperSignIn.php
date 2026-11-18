<?php

namespace Slakbal\TenancyFoundation\Filament\Pages\Concerns;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Livewire\Features\SupportRedirects\Redirector;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Enums\TenantResolution;

trait HasDeveloperSignIn
{
    public ?string $devAccount = null;

    public function signInAsDevAccount(): ?Redirector
    {
        abort_unless($this->devSignInEnabled(), 404);

        $panel = Filament::getCurrentPanel();
        $account = $this->devAccountQuery()->where('email', $this->devAccount)->first();

        if ($account === null || ! $account->canAccessPanel($panel)) {
            Notification::make()->title(__('Choose an account to sign in as.'))->warning()->send();

            return null;
        }

        $panel->auth()->login($account);
        session()->regenerate();

        return $this->redirect($panel->getUrl());
    }

    protected function devSignInEnabled(): bool
    {
        return ! app()->isProduction() && app()->isLocal() && config('tenancy-foundation.features.dev_sign_in');
    }

    protected function devSignInComponents(): array
    {
        if (! $this->devSignInEnabled()) {
            return [];
        }

        return [
            Select::make('devAccount')
                ->label(__('Sign in as (local only)'))
                ->options(fn (): array => $this->devAccountQuery()->orderBy('email')->get()->mapWithKeys(fn (Model $account): array => [$account->email => $account->name.' ('.$account->email.')'])->all())
                ->searchable(),
            Actions::make([
                Action::make('devSignIn')
                    ->label(__('Developer sign in'))
                    ->color('gray')
                    ->action(fn (): ?Redirector => $this->signInAsDevAccount()),
            ])->fullWidth(),
        ];
    }

    protected function devAccountQuery(): Builder|BelongsToMany
    {
        if (Filament::getCurrentPanel()->getId() === FilamentPanel::ADMIN->getPanelId()) {
            return config('tenancy-foundation.models.admin')::query();
        }

        $tenant = TenantResolution::current()->tenantForHost(request()->getHost());

        return $tenant !== null ? $tenant->members() : config('tenancy-foundation.models.user')::query()->whereRaw('1 = 0');
    }
}
