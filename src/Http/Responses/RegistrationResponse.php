<?php

namespace Slakbal\TenancyFoundation\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportRedirects\Redirector;

class RegistrationResponse implements RegistrationResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (filament()->getCurrentOrDefaultPanel()?->hasTenancy() && filament()->auth()->check()) {
            return redirect()->away(config('app.url').'/'.ltrim(config('tenancy-foundation.redirects.tenant_registration_path'), '/'));
        }

        $route = config('tenancy-foundation.redirects.logout_route');

        return redirect()->away($route && Route::has($route) ? route($route) : url('/'));
    }
}
