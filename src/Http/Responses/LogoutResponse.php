<?php

namespace Slakbal\TenancyFoundation\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Slakbal\TenancyFoundation\Enums\TenantResolution;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        if (TenantResolution::current() === TenantResolution::DOMAIN) {
            return redirect()->away(Filament::getCurrentPanel()?->hasLogin() ? Filament::getLoginUrl() : url('/'));
        }

        $route = config('tenancy-foundation.redirects.logout_route');

        return redirect()->away($route && Route::has($route) ? route($route) : url('/'));
    }
}
