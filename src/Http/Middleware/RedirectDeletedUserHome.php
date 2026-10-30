<?php

namespace Slakbal\TenancyFoundation\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectDeletedUserHome
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard(Filament::getAuthGuard());

        if ($guard->check() || ! $request->hasSession() || ! $request->session()->has($guard->getName())) {
            return $next($request);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(config('app.url'));
    }
}
