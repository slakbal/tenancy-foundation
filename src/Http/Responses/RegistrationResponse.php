<?php

namespace Slakbal\TenancyFoundation\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportRedirects\Redirector;

class RegistrationResponse implements RegistrationResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $panel = filament()->getCurrentOrDefaultPanel();
        $user = filament()->auth()->user();

        if ($panel?->hasTenancy() && $user) {
            if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail() && $panel->hasEmailVerification()) {
                return redirect()->to($panel->getEmailVerificationPromptUrl());
            }

            $tenant = $user->getDefaultTenant($panel);

            return redirect()->away($tenant
                ? appDomainScheme().$tenant->subdomain.'.'.config('tenancy-foundation.domain')
                : config('app.url').'/'.ltrim(config('tenancy-foundation.redirects.tenant_registration_path'), '/'));
        }

        $route = config('tenancy-foundation.redirects.logout_route');

        return redirect()->away($route && Route::has($route) ? route($route) : url('/'));
    }
}
