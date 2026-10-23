<?php

namespace Slakbal\TenancyFoundation\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        $route = config('tenancy-foundation.redirects.logout_route');

        return redirect()->away($route && Route::has($route) ? route($route) : url('/'));
    }
}
